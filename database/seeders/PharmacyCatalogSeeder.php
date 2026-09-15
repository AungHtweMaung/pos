<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class PharmacyCatalogSeeder extends Seeder
{
    /** Exactly this many products are seeded. */
    public const PRODUCT_COUNT = 1000;

    /** Catalogue is back-dated so it predates the demo sales history. */
    public const HISTORY_DAYS = 90;

    /**
     * Commercial tax per category. Medicines (and traditional medicine) are
     * zero-rated; retail health goods carry 5%.
     */
    private const TAXED_CATEGORIES = [
        'Vitamins & Supplements' => 5.00,
        'First Aid' => 5.00,
        'Medical Devices' => 5.00,
        'Baby Care' => 5.00,
        'Personal Care' => 5.00,
        'Oral Care' => 5.00,
        'Sexual Wellness' => 5.00,
    ];

    /**
     * Dosage forms: spec key => [name suffix, base unit label, packaging kind].
     * A null base unit label means the variant label is built from the size
     * (e.g. "60ml Bottle").
     */
    private const FORMS = [
        'tab' => ['Tablet', 'Tablet', 'strip'],
        'cap' => ['Capsule', 'Capsule', 'strip'],
        'mr' => ['MR Tablet', 'Tablet', 'strip'],
        'sr' => ['SR Tablet', 'Tablet', 'strip'],
        'chew' => ['Chewable Tablet', 'Tablet', 'strip'],
        'vag' => ['Vaginal Tablet', 'Tablet', 'strip'],
        'eff' => ['Effervescent Tablet', 'Tablet', 'tubepack'],
        'loz' => ['Lozenges', 'Lozenge', 'strip'],
        'syr' => ['Syrup', 'Bottle', 'sized'],
        'susp' => ['Suspension', 'Bottle', 'sized'],
        'drops' => ['Oral Drops', 'Bottle', 'sized'],
        'sol' => ['Solution', 'Bottle', 'sized'],
        'lotion' => ['Lotion', 'Bottle', 'sized'],
        'shampoo' => ['Shampoo', 'Bottle', 'sized'],
        'eye' => ['Eye Drops', 'Bottle', 'sized'],
        'ear' => ['Ear Drops', 'Bottle', 'sized'],
        'nasal' => ['Nasal Spray', 'Bottle', 'sized'],
        'cream' => ['Cream', 'Tube', 'sized'],
        'oint' => ['Ointment', 'Tube', 'sized'],
        'gel' => ['Gel', 'Tube', 'sized'],
        'vial' => ['Injection', 'Vial', 'ampoule'],
        'amp' => ['Injection', 'Ampoule', 'ampoule'],
        'inh' => ['Inhaler', 'Inhaler', 'single'],
        'sachet' => ['Sachet', 'Sachet', 'sachet'],
    ];

    /** Generic manufacturers with a cost multiplier relative to the base cost. */
    private const MAKERS = [
        'Cipla' => 1.00,
        'Sun Pharma' => 1.05,
        "Dr. Reddy's" => 1.05,
        'Zydus' => 0.95,
        'Mylan' => 1.10,
        'Micro Labs' => 0.90,
        'Mankind' => 0.90,
        'GPO' => 1.10,
        'Siam Pharmaceutical' => 1.15,
        'Berlin Pharma' => 1.15,
        'MPI' => 0.80,
        'FAME' => 0.85,
    ];

    private int $barcodeSeq = 100000001;

    /**
     * A Myanmar retail-pharmacy catalogue: generic medicines from several
     * manufacturers, branded medicines, and front-of-shop health goods.
     * Prices and costs are whole kyat (MMK); stock is in base units
     * (tablets, bottles, pieces). Barcodes are valid EAN-13 codes on
     * Myanmar's GS1 prefix (883). Generation is deterministic.
     */
    public function run(): void
    {
        mt_srand(2026);

        $admin = User::where('username', 'admin')->first();
        if (!$admin) {
            throw new RuntimeException('PharmacyCatalogSeeder needs the admin user to exist.');
        }

        $products = $this->buildCatalogue();
        $createdAt = Carbon::today()->subDays(self::HISTORY_DAYS + 1)->setTime(9, 0);
        $openingAt = $createdAt->copy()->setTime(18, 0);

        DB::transaction(function () use ($products, $admin, $createdAt, $openingAt) {
            $units = [];
            $adjustments = [];

            foreach ($products as $p) {
                $productId = DB::table('products')->insertGetId([
                    'name' => $p['name'],
                    'category' => $p['category'],
                    'tax_rate' => self::TAXED_CATEGORIES[$p['category']] ?? 0.00,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);

                foreach ($p['variants'] as [$label, $baseCost, $unitPacks]) {
                    $primaryPack = reset($unitPacks);
                    $primaryPrice = $this->price($baseCost * $primaryPack, $primaryPack);
                    [$stock, $threshold] = $this->stockFor($primaryPack, $primaryPrice);

                    $variantId = DB::table('variants')->insertGetId([
                        'product_id' => $productId,
                        'label' => $label,
                        'stock_qty' => $stock,
                        'low_stock_threshold' => $threshold,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ]);

                    foreach ($unitPacks as $unitLabel => $pack) {
                        $cost = (int) round($baseCost * $pack);
                        $units[] = [
                            'variant_id' => $variantId,
                            'label' => $unitLabel,
                            'barcode' => $this->nextBarcode(),
                            'pack_size' => $pack,
                            'price' => $this->price($cost, $pack),
                            'cost' => $cost,
                            'created_at' => $createdAt,
                            'updated_at' => $createdAt,
                        ];
                    }

                    if ($stock > 0) {
                        $adjustments[] = [
                            'variant_id' => $variantId,
                            'change_qty' => $stock,
                            'reason' => 'Opening stock',
                            'adjusted_by' => $admin->id,
                            'created_at' => $openingAt,
                            'updated_at' => $openingAt,
                        ];
                    }
                }
            }

            foreach (array_chunk($units, 500) as $chunk) {
                DB::table('sale_units')->insert($chunk);
            }
            foreach (array_chunk($adjustments, 500) as $chunk) {
                DB::table('stock_adjustments')->insert($chunk);
            }
        });
    }

    /**
     * Assemble exactly PRODUCT_COUNT products: every branded and retail item,
     * then generics filled in round-robin by manufacturer so each molecule
     * gets a similar number of makers.
     *
     * @return array<int, array{name: string, category: string, variants: array}>
     */
    private function buildCatalogue(): array
    {
        $products = [];

        foreach ($this->brandedMedicines() as [$name, $strength, $spec, $cost, $category]) {
            $products[] = $this->fromSpec($name, $strength, $spec, $cost, $category);
        }

        foreach ($this->retailItems() as [$name, $category, $variants]) {
            $products[] = [
                'name' => $name,
                'category' => $category,
                'variants' => array_map(
                    fn ($v) => [$v[0], $v[1], $v[2]],
                    $variants
                ),
            ];
        }

        // Each generic gets its own shuffled maker order; round r takes the
        // r-th maker of every generic.
        $generics = $this->genericMedicines();
        $makerOrders = [];
        foreach ($generics as $i => $_) {
            $makers = array_keys(self::MAKERS);
            $this->shuffle($makers);
            $makerOrders[$i] = $makers;
        }

        $round = 0;
        while (count($products) < self::PRODUCT_COUNT) {
            if ($round >= count(self::MAKERS)) {
                throw new RuntimeException('Not enough catalogue combinations to reach ' . self::PRODUCT_COUNT . ' products.');
            }
            foreach ($generics as $i => [$name, $strength, $spec, $cost, $category]) {
                if (count($products) >= self::PRODUCT_COUNT) {
                    break;
                }
                $maker = $makerOrders[$i][$round];
                $product = $this->fromSpec($name, $strength, $spec, $cost * self::MAKERS[$maker], $category);
                $product['name'] .= " ({$maker})";
                $products[] = $product;
            }
            $round++;
        }

        $names = array_column($products, 'name');
        if (count($names) !== count(array_unique($names))) {
            $dupes = array_keys(array_filter(array_count_values($names), fn ($c) => $c > 1));
            throw new RuntimeException('Duplicate product names: ' . implode(', ', $dupes));
        }

        return array_slice($products, 0, self::PRODUCT_COUNT);
    }

    /** Expand a compact medicine spec ("tab", "tab:4", "syr:60ml|100ml") into a product. */
    private function fromSpec(string $name, string $strength, string $spec, float $cost, string $category): array
    {
        [$form, $arg] = array_pad(explode(':', $spec, 2), 2, null);
        [$suffix, $baseLabel, $kind] = self::FORMS[$form];

        $variants = match ($kind) {
            'strip' => [$this->stripVariant($baseLabel, $cost, (int) ($arg ?? 10))],
            'tubepack' => [[$baseLabel, $cost, ['Tube of ' . ($arg ?? 10) => (int) ($arg ?? 10)]]],
            'ampoule' => [[$baseLabel, $cost, ["Single {$baseLabel}" => 1, 'Box of 10' => 10]]],
            'single' => [[$baseLabel, $cost, [$baseLabel => 1]]],
            'sachet' => [[$baseLabel, $cost, ['Single Sachet' => 1, 'Box of ' . ($arg ?? 20) => (int) ($arg ?? 20)]]],
            'sized' => $this->sizedVariants($baseLabel, $cost, $arg),
        };

        return [
            'name' => trim(preg_replace('/\s+/', ' ', "{$name} {$strength} {$suffix}")),
            'category' => $category,
            'variants' => $variants,
        ];
    }

    private function stripVariant(string $baseLabel, float $cost, int $strip): array
    {
        $units = ["Strip of {$strip}" => $strip];
        if ($strip >= 4 && mt_rand(1, 100) <= 65) {
            $units['Box of ' . ($strip * 10)] = $strip * 10;
        }

        return [$baseLabel, $cost, $units];
    }

    /** One variant per pack size, e.g. "60ml Bottle" and "120ml Bottle". Cost is for the first size. */
    private function sizedVariants(string $container, float $cost, ?string $sizes): array
    {
        $sizes = explode('|', $sizes ?? '');
        $first = (float) $sizes[0] ?: 1;
        $variants = [];

        foreach ($sizes as $i => $size) {
            // Larger packs are a little cheaper per ml/g.
            $sizeCost = $cost * ((float) $size ?: 1) / $first * ($i === 0 ? 1 : 0.9);
            $variants[] = [trim("{$size} {$container}"), round($sizeCost), [$container => 1]];
        }

        return $variants;
    }

    /** Retail price from cost: 18–40% margin, bulk packs a little less, rounded to kyat notes. */
    private function price(float $cost, int $pack): int
    {
        $markup = mt_rand(118, 140) / 100 - ($pack >= 50 ? 0.06 : 0);
        $raw = max($cost * $markup, $cost + 20);
        $step = $raw < 1000 ? 50 : ($raw < 10000 ? 100 : 500);

        return (int) (ceil($raw / $step) * $step);
    }

    /**
     * Opening stock in base units, sized by how expensive the primary unit is.
     * ~8% of variants opt out of low-stock alerts (null threshold).
     *
     * @return array{0: int, 1: int|null}
     */
    private function stockFor(int $primaryPack, int $primaryPrice): array
    {
        [$min, $max, $alertAt] = match (true) {
            $primaryPrice < 2000 => [30, 160, 15],
            $primaryPrice < 10000 => [10, 60, 6],
            $primaryPrice < 50000 => [4, 25, 3],
            default => [1, 6, 1],
        };

        $stock = mt_rand($min, $max) * $primaryPack;
        $threshold = mt_rand(1, 100) <= 8 ? null : $alertAt * $primaryPack;

        return [$stock, $threshold];
    }

    /** Sequential EAN-13 on the Myanmar GS1 prefix, with a valid check digit. */
    private function nextBarcode(): string
    {
        $digits = '883' . str_pad((string) $this->barcodeSeq++, 9, '0', STR_PAD_LEFT);
        $sum = 0;
        foreach (str_split($digits) as $i => $d) {
            $sum += (int) $d * ($i % 2 === 0 ? 1 : 3);
        }

        return $digits . ((10 - $sum % 10) % 10);
    }

    /** Fisher–Yates on mt_rand so the seeded order is reproducible. */
    private function shuffle(array &$items): void
    {
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
    }

    /**
     * Generic medicines: [molecule, strength, form spec, base cost (MMK per
     * tablet / per pack), category]. Each is sold under several makers.
     */
    private function genericMedicines(): array
    {
        return [
            // Analgesics & Antipyretics
            ['Paracetamol', '500mg', 'tab', 30, 'Analgesics & Antipyretics'],
            ['Paracetamol', '650mg', 'tab', 45, 'Analgesics & Antipyretics'],
            ['Paracetamol', '120mg/5ml', 'syr:60ml|100ml', 1500, 'Analgesics & Antipyretics'],
            ['Paracetamol', '100mg/ml', 'drops:15ml', 2200, 'Analgesics & Antipyretics'],
            ['Ibuprofen', '200mg', 'tab', 40, 'Analgesics & Antipyretics'],
            ['Ibuprofen', '400mg', 'tab', 60, 'Analgesics & Antipyretics'],
            ['Ibuprofen', '100mg/5ml', 'susp:60ml|100ml', 2200, 'Analgesics & Antipyretics'],
            ['Diclofenac Sodium', '50mg', 'tab', 50, 'Analgesics & Antipyretics'],
            ['Diclofenac Sodium', '75mg/3ml', 'amp', 900, 'Analgesics & Antipyretics'],
            ['Diclofenac Diethylamine', '1%', 'gel:30g', 2800, 'Analgesics & Antipyretics'],
            ['Mefenamic Acid', '250mg', 'cap', 60, 'Analgesics & Antipyretics'],
            ['Mefenamic Acid', '500mg', 'tab', 90, 'Analgesics & Antipyretics'],
            ['Naproxen', '250mg', 'tab', 120, 'Analgesics & Antipyretics'],
            ['Aspirin', '300mg', 'tab', 25, 'Analgesics & Antipyretics'],
            ['Tramadol', '50mg', 'cap', 150, 'Analgesics & Antipyretics'],
            ['Celecoxib', '200mg', 'cap', 350, 'Analgesics & Antipyretics'],
            ['Etoricoxib', '90mg', 'tab', 450, 'Analgesics & Antipyretics'],
            ['Orphenadrine + Paracetamol', '35mg/450mg', 'tab', 120, 'Analgesics & Antipyretics'],

            // Antibiotics
            ['Amoxicillin', '250mg', 'cap', 80, 'Antibiotics'],
            ['Amoxicillin', '500mg', 'cap', 130, 'Antibiotics'],
            ['Amoxicillin', '125mg/5ml', 'susp:60ml|100ml', 1800, 'Antibiotics'],
            ['Amoxicillin + Clavulanic Acid', '625mg', 'tab:6', 650, 'Antibiotics'],
            ['Amoxicillin + Clavulanic Acid', '1g', 'tab:6', 900, 'Antibiotics'],
            ['Amoxicillin + Clavulanic Acid', '228mg/5ml', 'susp:70ml', 6500, 'Antibiotics'],
            ['Ampicillin', '500mg', 'cap', 150, 'Antibiotics'],
            ['Cloxacillin', '500mg', 'cap', 180, 'Antibiotics'],
            ['Cephalexin', '250mg', 'cap', 120, 'Antibiotics'],
            ['Cephalexin', '500mg', 'cap', 200, 'Antibiotics'],
            ['Cefuroxime Axetil', '500mg', 'tab', 900, 'Antibiotics'],
            ['Cefixime', '200mg', 'cap', 700, 'Antibiotics'],
            ['Cefixime', '100mg/5ml', 'susp:30ml', 5500, 'Antibiotics'],
            ['Azithromycin', '250mg', 'tab:6', 500, 'Antibiotics'],
            ['Azithromycin', '500mg', 'tab:3', 850, 'Antibiotics'],
            ['Azithromycin', '200mg/5ml', 'susp:15ml|30ml', 4500, 'Antibiotics'],
            ['Clarithromycin', '500mg', 'tab', 900, 'Antibiotics'],
            ['Erythromycin', '250mg', 'tab', 150, 'Antibiotics'],
            ['Ciprofloxacin', '500mg', 'tab', 150, 'Antibiotics'],
            ['Levofloxacin', '500mg', 'tab', 400, 'Antibiotics'],
            ['Ofloxacin', '200mg', 'tab', 180, 'Antibiotics'],
            ['Doxycycline', '100mg', 'cap', 100, 'Antibiotics'],
            ['Metronidazole', '200mg', 'tab', 40, 'Antibiotics'],
            ['Metronidazole', '400mg', 'tab', 60, 'Antibiotics'],
            ['Co-trimoxazole', '480mg', 'tab', 50, 'Antibiotics'],
            ['Nitrofurantoin', '100mg', 'cap', 200, 'Antibiotics'],
            ['Clindamycin', '300mg', 'cap', 400, 'Antibiotics'],
            ['Ceftriaxone', '1g', 'vial', 2500, 'Antibiotics'],

            // Antihistamines & Allergy
            ['Cetirizine', '10mg', 'tab', 40, 'Antihistamines & Allergy'],
            ['Cetirizine', '5mg/5ml', 'syr:60ml', 2000, 'Antihistamines & Allergy'],
            ['Levocetirizine', '5mg', 'tab', 80, 'Antihistamines & Allergy'],
            ['Loratadine', '10mg', 'tab', 50, 'Antihistamines & Allergy'],
            ['Desloratadine', '5mg', 'tab', 250, 'Antihistamines & Allergy'],
            ['Fexofenadine', '120mg', 'tab', 250, 'Antihistamines & Allergy'],
            ['Fexofenadine', '180mg', 'tab', 350, 'Antihistamines & Allergy'],
            ['Chlorpheniramine Maleate', '4mg', 'tab', 10, 'Antihistamines & Allergy'],
            ['Promethazine', '25mg', 'tab', 40, 'Antihistamines & Allergy'],

            // Cough & Cold
            ['Dextromethorphan', '15mg/5ml', 'syr:100ml', 2500, 'Cough & Cold'],
            ['Guaifenesin', '100mg/5ml', 'syr:100ml', 2300, 'Cough & Cold'],
            ['Bromhexine', '8mg', 'tab', 40, 'Cough & Cold'],
            ['Bromhexine', '4mg/5ml', 'syr:60ml|120ml', 1800, 'Cough & Cold'],
            ['Ambroxol', '30mg', 'tab', 60, 'Cough & Cold'],
            ['Ambroxol', '15mg/5ml', 'syr:100ml', 2500, 'Cough & Cold'],
            ['Carbocisteine', '375mg', 'cap', 120, 'Cough & Cold'],
            ['Paracetamol + Phenylephrine + Chlorpheniramine', '', 'tab', 80, 'Cough & Cold'],
            ['Pseudoephedrine', '60mg', 'tab', 70, 'Cough & Cold'],
            ['Xylometazoline', '0.1%', 'nasal:10ml', 2500, 'Cough & Cold'],

            // Gastrointestinal
            ['Omeprazole', '20mg', 'cap', 90, 'Gastrointestinal'],
            ['Esomeprazole', '40mg', 'tab', 350, 'Gastrointestinal'],
            ['Pantoprazole', '40mg', 'tab', 250, 'Gastrointestinal'],
            ['Lansoprazole', '30mg', 'cap', 200, 'Gastrointestinal'],
            ['Rabeprazole', '20mg', 'tab', 250, 'Gastrointestinal'],
            ['Famotidine', '20mg', 'tab', 80, 'Gastrointestinal'],
            ['Domperidone', '10mg', 'tab', 50, 'Gastrointestinal'],
            ['Domperidone', '5mg/5ml', 'susp:60ml', 2200, 'Gastrointestinal'],
            ['Metoclopramide', '10mg', 'tab', 30, 'Gastrointestinal'],
            ['Ondansetron', '4mg', 'tab', 200, 'Gastrointestinal'],
            ['Loperamide', '2mg', 'cap', 50, 'Gastrointestinal'],
            ['Hyoscine Butylbromide', '10mg', 'tab', 90, 'Gastrointestinal'],
            ['Aluminium + Magnesium Hydroxide', '', 'susp:200ml', 3000, 'Gastrointestinal'],
            ['Aluminium + Magnesium Hydroxide + Simethicone', '', 'chew', 40, 'Gastrointestinal'],
            ['Oral Rehydration Salts', '20.5g', 'sachet:25', 250, 'Gastrointestinal'],
            ['Bisacodyl', '5mg', 'tab', 40, 'Gastrointestinal'],
            ['Lactulose', '3.3g/5ml', 'syr:100ml|200ml', 5500, 'Gastrointestinal'],
            ['Simethicone', '40mg', 'chew', 80, 'Gastrointestinal'],

            // Antifungal & Antiparasitic
            ['Mebendazole', '100mg', 'chew:6', 150, 'Antifungal & Antiparasitic'],
            ['Albendazole', '400mg', 'chew:1', 400, 'Antifungal & Antiparasitic'],
            ['Albendazole', '200mg/5ml', 'susp:10ml', 1500, 'Antifungal & Antiparasitic'],
            ['Clotrimazole', '1%', 'cream:15g', 1500, 'Antifungal & Antiparasitic'],
            ['Clotrimazole', '500mg', 'vag:1', 1500, 'Antifungal & Antiparasitic'],
            ['Miconazole Nitrate', '2%', 'cream:15g', 2000, 'Antifungal & Antiparasitic'],
            ['Ketoconazole', '2%', 'cream:15g', 2500, 'Antifungal & Antiparasitic'],
            ['Ketoconazole', '2%', 'shampoo:100ml', 6500, 'Antifungal & Antiparasitic'],
            ['Terbinafine', '1%', 'cream:15g', 3500, 'Antifungal & Antiparasitic'],
            ['Fluconazole', '150mg', 'cap:1', 500, 'Antifungal & Antiparasitic'],
            ['Permethrin', '5%', 'cream:30g', 4500, 'Antifungal & Antiparasitic'],
            ['Artemether + Lumefantrine', '20mg/120mg', 'tab:24', 300, 'Antifungal & Antiparasitic'],
            ['Primaquine', '15mg', 'tab', 60, 'Antifungal & Antiparasitic'],

            // Cardiovascular
            ['Amlodipine', '5mg', 'tab', 50, 'Cardiovascular'],
            ['Amlodipine', '10mg', 'tab', 80, 'Cardiovascular'],
            ['Nifedipine', '20mg', 'sr', 70, 'Cardiovascular'],
            ['Losartan Potassium', '50mg', 'tab', 120, 'Cardiovascular'],
            ['Valsartan', '80mg', 'tab', 300, 'Cardiovascular'],
            ['Telmisartan', '40mg', 'tab', 200, 'Cardiovascular'],
            ['Enalapril', '5mg', 'tab', 50, 'Cardiovascular'],
            ['Lisinopril', '10mg', 'tab', 90, 'Cardiovascular'],
            ['Atenolol', '50mg', 'tab', 40, 'Cardiovascular'],
            ['Bisoprolol', '5mg', 'tab', 150, 'Cardiovascular'],
            ['Metoprolol Tartrate', '50mg', 'tab', 70, 'Cardiovascular'],
            ['Hydrochlorothiazide', '25mg', 'tab', 30, 'Cardiovascular'],
            ['Furosemide', '40mg', 'tab', 30, 'Cardiovascular'],
            ['Spironolactone', '25mg', 'tab', 90, 'Cardiovascular'],
            ['Atorvastatin', '10mg', 'tab', 120, 'Cardiovascular'],
            ['Atorvastatin', '20mg', 'tab', 180, 'Cardiovascular'],
            ['Rosuvastatin', '10mg', 'tab', 250, 'Cardiovascular'],
            ['Simvastatin', '20mg', 'tab', 90, 'Cardiovascular'],
            ['Clopidogrel', '75mg', 'tab', 180, 'Cardiovascular'],
            ['Aspirin', '81mg', 'tab', 30, 'Cardiovascular'],
            ['Isosorbide Dinitrate', '5mg', 'tab', 50, 'Cardiovascular'],

            // Diabetes
            ['Metformin', '500mg', 'tab', 40, 'Diabetes'],
            ['Metformin', '850mg', 'tab', 60, 'Diabetes'],
            ['Metformin', '1000mg', 'sr', 90, 'Diabetes'],
            ['Glibenclamide', '5mg', 'tab', 25, 'Diabetes'],
            ['Gliclazide', '80mg', 'tab', 60, 'Diabetes'],
            ['Gliclazide', '30mg', 'mr', 120, 'Diabetes'],
            ['Glimepiride', '2mg', 'tab', 120, 'Diabetes'],
            ['Sitagliptin', '100mg', 'tab', 1200, 'Diabetes'],
            ['Vildagliptin', '50mg', 'tab', 700, 'Diabetes'],
            ['Pioglitazone', '15mg', 'tab', 150, 'Diabetes'],

            // Respiratory
            ['Salbutamol', '2mg', 'tab', 20, 'Respiratory'],
            ['Salbutamol', '2mg/5ml', 'syr:100ml', 1800, 'Respiratory'],
            ['Salbutamol', '100mcg', 'inh', 6500, 'Respiratory'],
            ['Budesonide', '200mcg', 'inh', 18000, 'Respiratory'],
            ['Montelukast', '10mg', 'tab', 300, 'Respiratory'],
            ['Montelukast', '4mg', 'chew', 250, 'Respiratory'],
            ['Theophylline', '200mg', 'sr', 80, 'Respiratory'],

            // Hormones & Steroids
            ['Prednisolone', '5mg', 'tab', 30, 'Hormones & Steroids'],
            ['Dexamethasone', '0.5mg', 'tab', 20, 'Hormones & Steroids'],
            ['Levothyroxine', '50mcg', 'tab', 50, 'Hormones & Steroids'],
            ['Carbimazole', '5mg', 'tab', 60, 'Hormones & Steroids'],

            // Neurology & Mental Health
            ['Amitriptyline', '25mg', 'tab', 30, 'Neurology & Mental Health'],
            ['Diazepam', '5mg', 'tab', 30, 'Neurology & Mental Health'],
            ['Carbamazepine', '200mg', 'tab', 80, 'Neurology & Mental Health'],
            ['Gabapentin', '300mg', 'cap', 250, 'Neurology & Mental Health'],
            ['Betahistine', '16mg', 'tab', 150, 'Neurology & Mental Health'],
            ['Cinnarizine', '25mg', 'tab', 30, 'Neurology & Mental Health'],
            ['Flunarizine', '5mg', 'cap', 80, 'Neurology & Mental Health'],

            // Dermatology
            ['Betamethasone Valerate', '0.1%', 'cream:15g', 1800, 'Dermatology'],
            ['Hydrocortisone', '1%', 'cream:15g', 1500, 'Dermatology'],
            ['Fusidic Acid', '2%', 'cream:15g', 4500, 'Dermatology'],
            ['Mupirocin', '2%', 'oint:15g', 5500, 'Dermatology'],
            ['Silver Sulfadiazine', '1%', 'cream:25g', 3500, 'Dermatology'],
            ['Calamine', '', 'lotion:100ml', 2500, 'Dermatology'],
            ['Acyclovir', '5%', 'cream:5g', 2500, 'Dermatology'],
            ['Acyclovir', '400mg', 'tab', 250, 'Dermatology'],
            ['Benzoyl Peroxide', '5%', 'gel:20g', 4000, 'Dermatology'],
            ['Adapalene', '0.1%', 'gel:15g', 7500, 'Dermatology'],

            // Eye & Ear Care
            ['Chloramphenicol', '0.5%', 'eye:10ml', 1500, 'Eye & Ear Care'],
            ['Tobramycin', '0.3%', 'eye:5ml', 3500, 'Eye & Ear Care'],
            ['Ofloxacin', '0.3%', 'eye:5ml', 3000, 'Eye & Ear Care'],
            ['Olopatadine', '0.1%', 'eye:5ml', 8000, 'Eye & Ear Care'],
            ['Ciprofloxacin', '0.3%', 'ear:10ml', 2500, 'Eye & Ear Care'],

            // Vitamins & Supplements
            ['Vitamin C', '500mg', 'tab', 60, 'Vitamins & Supplements'],
            ['Vitamin B Complex', '', 'tab', 30, 'Vitamins & Supplements'],
            ['Vitamin B1 B6 B12', '', 'tab', 120, 'Vitamins & Supplements'],
            ['Vitamin E', '400IU', 'cap', 180, 'Vitamins & Supplements'],
            ['Vitamin D3', '1000IU', 'cap', 120, 'Vitamins & Supplements'],
            ['Folic Acid', '5mg', 'tab', 15, 'Vitamins & Supplements'],
            ['Ferrous Sulphate + Folic Acid', '', 'tab', 25, 'Vitamins & Supplements'],
            ['Calcium Carbonate + Vitamin D3', '500mg', 'tab', 90, 'Vitamins & Supplements'],
            ['Zinc Sulphate', '20mg', 'tab', 60, 'Vitamins & Supplements'],
            ['Multivitamin', '', 'syr:100ml|200ml', 3500, 'Vitamins & Supplements'],

            // Women's Health & Urology
            ['Levonorgestrel', '1.5mg', 'tab:1', 3000, "Women's Health"],
            ['Levonorgestrel + Ethinylestradiol', '0.15mg/0.03mg', 'tab:28', 50, "Women's Health"],
            ['Norethisterone', '5mg', 'tab', 150, "Women's Health"],
            ['Tamsulosin', '0.4mg', 'cap', 400, 'Urology'],
            ['Finasteride', '5mg', 'tab', 450, 'Urology'],
            ['Sildenafil', '50mg', 'tab:4', 1200, 'Urology'],
        ];
    }

    /** Branded medicines: [brand, strength, form spec, base cost (MMK), category]. */
    private function brandedMedicines(): array
    {
        return [
            ['Biogesic', '500mg', 'tab', 60, 'Analgesics & Antipyretics'],
            ['Panadol', '500mg', 'tab', 70, 'Analgesics & Antipyretics'],
            ['Panadol Extra', '', 'tab', 100, 'Analgesics & Antipyretics'],
            ['Panadol Actifast', '500mg', 'tab', 120, 'Analgesics & Antipyretics'],
            ['Calpol Paediatric', '120mg/5ml', 'susp:60ml|100ml', 4500, 'Analgesics & Antipyretics'],
            ['Brufen', '400mg', 'tab', 150, 'Analgesics & Antipyretics'],
            ['Ponstan', '500mg', 'tab', 200, 'Analgesics & Antipyretics'],
            ['Voltaren', '50mg', 'tab', 250, 'Analgesics & Antipyretics'],
            ['Voltaren Emulgel', '1%', 'gel:20g|50g', 5500, 'Analgesics & Antipyretics'],
            ['Mydocalm', '50mg', 'tab', 250, 'Analgesics & Antipyretics'],
            ['Norgesic', '', 'tab', 200, 'Analgesics & Antipyretics'],
            ['Arcoxia', '90mg', 'tab:7', 1800, 'Analgesics & Antipyretics'],
            ['Celebrex', '200mg', 'cap', 1500, 'Analgesics & Antipyretics'],
            ['Decolgen Forte', '', 'tab', 120, 'Cough & Cold'],
            ['Neozep Forte', '', 'tab', 110, 'Cough & Cold'],
            ['Tiffy Dey', '', 'tab:4', 110, 'Cough & Cold'],
            ['Bioflu', '', 'tab', 130, 'Cough & Cold'],
            ['Actifed', '', 'tab', 150, 'Cough & Cold'],
            ['Actifed DM', '', 'syr:100ml', 6500, 'Cough & Cold'],
            ['Bisolvon', '8mg', 'tab', 150, 'Cough & Cold'],
            ['Bisolvon Elixir', '4mg/5ml', 'syr:60ml', 4800, 'Cough & Cold'],
            ['Mucosolvan', '30mg', 'tab', 220, 'Cough & Cold'],
            ['Solmux', '500mg', 'cap', 200, 'Cough & Cold'],
            ['Strepsils Honey & Lemon', '', 'loz:8', 120, 'Cough & Cold'],
            ['Strepsils Original', '', 'loz:8', 120, 'Cough & Cold'],
            ['Dequadin', '', 'loz:10', 90, 'Cough & Cold'],
            ['Otrivin', '0.1%', 'nasal:10ml', 5500, 'Cough & Cold'],
            ['Augmentin', '625mg', 'tab:7', 1800, 'Antibiotics'],
            ['Augmentin', '1g', 'tab:7', 2600, 'Antibiotics'],
            ['Amoxil', '500mg', 'cap', 300, 'Antibiotics'],
            ['Zinnat', '500mg', 'tab', 2500, 'Antibiotics'],
            ['Ciprobay', '500mg', 'tab', 1200, 'Antibiotics'],
            ['Klacid', '500mg', 'tab:7', 2200, 'Antibiotics'],
            ['Zithromax', '250mg', 'tab:6', 2800, 'Antibiotics'],
            ['Flagyl', '400mg', 'tab', 150, 'Antibiotics'],
            ['Zyrtec', '10mg', 'tab', 350, 'Antihistamines & Allergy'],
            ['Clarityne', '10mg', 'tab', 400, 'Antihistamines & Allergy'],
            ['Telfast', '180mg', 'tab', 800, 'Antihistamines & Allergy'],
            ['Aerius', '5mg', 'tab', 750, 'Antihistamines & Allergy'],
            ['Losec', '20mg', 'cap:7', 900, 'Gastrointestinal'],
            ['Nexium', '40mg', 'tab:7', 1800, 'Gastrointestinal'],
            ['Motilium', '10mg', 'tab', 200, 'Gastrointestinal'],
            ['Buscopan', '10mg', 'tab', 250, 'Gastrointestinal'],
            ['Smecta', '3g', 'sachet:30', 800, 'Gastrointestinal'],
            ['Imodium', '2mg', 'cap:6', 350, 'Gastrointestinal'],
            ['Gaviscon Double Action', '', 'susp:150ml', 9500, 'Gastrointestinal'],
            ['Eno Fruit Salt Lemon', '5g', 'sachet:12', 300, 'Gastrointestinal'],
            ['Dulcolax', '5mg', 'tab', 200, 'Gastrointestinal'],
            ['Norvasc', '5mg', 'tab', 800, 'Cardiovascular'],
            ['Cozaar', '50mg', 'tab', 900, 'Cardiovascular'],
            ['Micardis', '40mg', 'tab:7', 1000, 'Cardiovascular'],
            ['Lipitor', '20mg', 'tab', 1800, 'Cardiovascular'],
            ['Crestor', '10mg', 'tab:7', 1600, 'Cardiovascular'],
            ['Plavix', '75mg', 'tab:14', 2200, 'Cardiovascular'],
            ['Concor', '5mg', 'tab', 700, 'Cardiovascular'],
            ['Glucophage', '500mg', 'tab', 150, 'Diabetes'],
            ['Glucophage XR', '750mg', 'tab', 350, 'Diabetes'],
            ['Diamicron MR', '60mg', 'tab:15', 450, 'Diabetes'],
            ['Amaryl', '2mg', 'tab', 600, 'Diabetes'],
            ['Januvia', '100mg', 'tab:14', 3500, 'Diabetes'],
            ['Galvus Met', '50mg/850mg', 'tab:14', 900, 'Diabetes'],
            ['Ventolin Evohaler', '100mcg', 'inh', 9500, 'Respiratory'],
            ['Seretide Accuhaler', '50mcg/250mcg', 'inh', 45000, 'Respiratory'],
            ['Symbicort Turbuhaler', '160mcg/4.5mcg', 'inh', 52000, 'Respiratory'],
            ['Singulair', '10mg', 'tab:14', 1500, 'Respiratory'],
            ['Canesten', '1%', 'cream:20g', 6500, 'Antifungal & Antiparasitic'],
            ['Daktarin', '2%', 'cream:15g', 6000, 'Antifungal & Antiparasitic'],
            ['Lamisil', '1%', 'cream:15g', 9000, 'Antifungal & Antiparasitic'],
            ['Nizoral', '2%', 'shampoo:100ml', 14000, 'Antifungal & Antiparasitic'],
            ['Diflucan', '150mg', 'cap:1', 4500, 'Antifungal & Antiparasitic'],
            ['Combantrin', '250mg', 'chew:2', 900, 'Antifungal & Antiparasitic'],
            ['Zentel', '400mg', 'chew:1', 1800, 'Antifungal & Antiparasitic'],
            ['Coartem', '20mg/120mg', 'tab:24', 700, 'Antifungal & Antiparasitic'],
            ['Betnovate', '0.1%', 'cream:15g', 4500, 'Dermatology'],
            ['Fucidin', '2%', 'cream:15g', 9500, 'Dermatology'],
            ['Bactroban', '2%', 'oint:15g', 11000, 'Dermatology'],
            ['Bepanthen', '5%', 'oint:30g', 9500, 'Dermatology'],
            ['Zovirax', '5%', 'cream:5g', 8500, 'Dermatology'],
            ['Differin', '0.1%', 'gel:30g', 16000, 'Dermatology'],
            ['Tears Naturale II', '', 'eye:15ml', 9000, 'Eye & Ear Care'],
            ['Visine Original', '', 'eye:15ml', 6500, 'Eye & Ear Care'],
            ['Systane Ultra', '', 'eye:10ml', 14000, 'Eye & Ear Care'],
            ['Serc', '16mg', 'tab', 400, 'Neurology & Mental Health'],
            ['Stugeron', '25mg', 'tab', 150, 'Neurology & Mental Health'],
            ['Neurobion', '', 'tab', 250, 'Vitamins & Supplements'],
            ['Berocca Performance', '', 'eff:10', 900, 'Vitamins & Supplements'],
            ['Redoxon Double Action', '1000mg', 'eff:10', 800, 'Vitamins & Supplements'],
            ['Enervon-C', '', 'tab', 150, 'Vitamins & Supplements'],
            ['Obimin', '', 'tab', 250, 'Vitamins & Supplements'],
            ['Centrum Adults', '', 'tab:30', 600, 'Vitamins & Supplements'],
            ['Caltrate 600+D3', '', 'tab:30', 450, 'Vitamins & Supplements'],
            ['Sangobion', '', 'cap', 250, 'Vitamins & Supplements'],
            ["Scott's Emulsion Original", '', 'syr:200ml|400ml', 6500, 'Vitamins & Supplements'],
            ['Blackmores Fish Oil', '1000mg', 'cap:30', 450, 'Vitamins & Supplements'],
            ['Blackmores Bio C', '1000mg', 'tab:31', 550, 'Vitamins & Supplements'],
            ['Seven Seas Cod Liver Oil', '', 'cap:30', 350, 'Vitamins & Supplements'],
            ['Postinor-1', '1.5mg', 'tab:1', 5500, "Women's Health"],
            ['Yasmin', '', 'tab:21', 700, "Women's Health"],
            ['Duphaston', '10mg', 'tab:20', 1200, "Women's Health"],
            ['Viagra', '50mg', 'tab:4', 12000, 'Urology'],
            ['Cialis', '20mg', 'tab:4', 15000, 'Urology'],
        ];
    }

    /**
     * Non-medicine shelf items: [name, category, [[variant label, base cost,
     * [sale unit label => pack size]], ...]].
     */
    private function retailItems(): array
    {
        $each = fn (string $label = 'Each') => [$label => 1];

        return [
            // First Aid
            ['Hansaplast Fabric Plasters', 'First Aid', [['Plaster', 60, ['Pack of 10' => 10, 'Box of 100' => 100]]]],
            ['Hansaplast Waterproof Plasters', 'First Aid', [['Plaster', 80, ['Pack of 10' => 10, 'Box of 20' => 20]]]],
            ['Band-Aid Flexible Fabric', 'First Aid', [['Plaster', 90, ['Box of 30' => 30]]]],
            ['Absorbent Cotton Wool', 'First Aid', [['50g Roll', 1000, $each('Roll')], ['100g Roll', 1800, $each('Roll')], ['500g Roll', 7500, $each('Roll')]]],
            ['Sterile Gauze Swab 10x10cm', 'First Aid', [['Swab', 60, ['Pack of 10' => 10, 'Box of 100' => 100]]]],
            ['Elastic Crepe Bandage', 'First Aid', [['4 inch', 1500, $each('Roll')], ['6 inch', 2000, $each('Roll')]]],
            ['Micropore Surgical Tape', 'First Aid', [['0.5 inch', 900, $each('Roll')], ['1 inch', 1500, $each('Roll')]]],
            ['Triangular Bandage', 'First Aid', [['Piece', 1500, $each()]]],
            ['Sterile Eye Pad', 'First Aid', [['Piece', 250, $each(), ]]],
            ['Povidone-Iodine 10% Solution', 'First Aid', [['30ml Bottle', 1500, $each('Bottle')], ['60ml Bottle', 2500, $each('Bottle')], ['500ml Bottle', 14000, $each('Bottle')]]],
            ['Betadine Antiseptic Solution', 'First Aid', [['15ml Bottle', 2500, $each('Bottle')], ['60ml Bottle', 5000, $each('Bottle')]]],
            ['Dettol Antiseptic Liquid', 'First Aid', [['100ml Bottle', 3500, $each('Bottle')], ['500ml Bottle', 12000, $each('Bottle')], ['1L Bottle', 21000, $each('Bottle')]]],
            ['Hydrogen Peroxide 3%', 'First Aid', [['100ml Bottle', 1200, $each('Bottle')]]],
            ['Surgical Spirit 70%', 'First Aid', [['100ml Bottle', 1500, $each('Bottle')], ['450ml Bottle', 4500, $each('Bottle')]]],
            ['Alcohol Prep Pads', 'First Aid', [['Pad', 40, ['Box of 100' => 100]]]],
            ['Hand Sanitizer Gel 70%', 'First Aid', [['60ml Bottle', 1500, $each('Bottle')], ['500ml Pump', 5000, $each('Bottle')]]],
            ['Disposable Syringe 3ml', 'First Aid', [['Syringe', 150, ['Single' => 1, 'Box of 100' => 100]]]],
            ['Disposable Syringe 5ml', 'First Aid', [['Syringe', 170, ['Single' => 1, 'Box of 100' => 100]]]],
            ['Insulin Syringe 1ml', 'First Aid', [['Syringe', 200, ['Single' => 1, 'Box of 100' => 100]]]],
            ['3-Ply Surgical Face Mask', 'First Aid', [['Mask', 60, ['Pack of 5' => 5, 'Box of 50' => 50]]]],
            ['KN95 Face Mask', 'First Aid', [['Mask', 400, ['Single' => 1, 'Box of 20' => 20]]]],
            ['Nitrile Examination Gloves', 'First Aid', [['M', 120, ['Box of 100' => 100]], ['L', 120, ['Box of 100' => 100]]]],
            ['Instant Cold Pack', 'First Aid', [['Pack', 2500, $each('Pack')]]],
            ['Burn Relief Gel', 'First Aid', [['25g Tube', 4500, $each('Tube')]]],
            ['Salonpas Pain Relief Patch', 'First Aid', [['Patch', 150, ['Pack of 10' => 10, 'Pack of 20' => 20]]]],

            // Medical Devices
            ['Omron HEM-7120 Blood Pressure Monitor', 'Medical Devices', [['Unit', 75000, $each('Unit')]]],
            ['Omron HEM-7156 Blood Pressure Monitor', 'Medical Devices', [['Unit', 120000, $each('Unit')]]],
            ['Yuwell YE660D Blood Pressure Monitor', 'Medical Devices', [['Unit', 45000, $each('Unit')]]],
            ['Accu-Chek Instant Glucometer', 'Medical Devices', [['Unit', 45000, $each('Kit')]]],
            ['Accu-Chek Instant Test Strips', 'Medical Devices', [['Strip', 700, ['Box of 50' => 50]]]],
            ['On Call Plus Glucometer', 'Medical Devices', [['Unit', 30000, $each('Kit')]]],
            ['On Call Plus Test Strips', 'Medical Devices', [['Strip', 450, ['Box of 50' => 50]]]],
            ['Blood Lancets 28G', 'Medical Devices', [['Lancet', 60, ['Box of 100' => 100]]]],
            ['Digital Thermometer', 'Medical Devices', [['Unit', 3500, $each('Unit')]]],
            ['Infrared Forehead Thermometer', 'Medical Devices', [['Unit', 25000, $each('Unit')]]],
            ['Fingertip Pulse Oximeter', 'Medical Devices', [['Unit', 20000, $each('Unit')]]],
            ['Compressor Nebulizer', 'Medical Devices', [['Unit', 60000, $each('Unit')]]],
            ['Nebulizer Mask Kit', 'Medical Devices', [['Adult', 4500, $each('Kit')], ['Child', 4500, $each('Kit')]]],
            ['Pregnancy Test Kit', 'Medical Devices', [['Test', 1200, $each('Test')]]],
            ['Ovulation Test Strip', 'Medical Devices', [['Strip', 900, ['Single' => 1, 'Pack of 10' => 10]]]],
            ['Knee Support', 'Medical Devices', [['M', 8000, $each('Piece')], ['L', 8000, $each('Piece')], ['XL', 8500, $each('Piece')]]],
            ['Lumbar Support Belt', 'Medical Devices', [['M', 18000, $each('Piece')], ['L', 18000, $each('Piece')]]],
            ['Wrist Splint', 'Medical Devices', [['Left', 9000, $each('Piece')], ['Right', 9000, $each('Piece')]]],
            ['Soft Cervical Collar', 'Medical Devices', [['Universal', 7000, $each('Piece')]]],
            ['Rubber Hot Water Bag', 'Medical Devices', [['2L', 4500, $each('Piece')]]],
            ['Aluminium Walking Stick', 'Medical Devices', [['Adjustable', 15000, $each('Piece')]]],
            ['Folding Wheelchair', 'Medical Devices', [['Standard', 180000, $each('Unit')]]],
            ['Stethoscope', 'Medical Devices', [['Dual Head', 25000, $each('Unit')]]],
            ['Urine Drainage Bag 2L', 'Medical Devices', [['Bag', 1500, ['Single' => 1, 'Pack of 10' => 10]]]],
            ['Adult Diapers', 'Medical Devices', [['M', 800, ['Pack of 10' => 10]], ['L', 850, ['Pack of 10' => 10]], ['XL', 900, ['Pack of 10' => 10]]]],
            ['Underpads 60x90cm', 'Medical Devices', [['Pad', 500, ['Pack of 10' => 10]]]],

            // Sexual Wellness
            ['Durex Classic Condoms', 'Sexual Wellness', [['Condom', 900, ['Pack of 3' => 3, 'Pack of 12' => 12]]]],
            ['Durex Fetherlite Condoms', 'Sexual Wellness', [['Condom', 1100, ['Pack of 3' => 3, 'Pack of 12' => 12]]]],
            ['Okamoto 003 Condoms', 'Sexual Wellness', [['Condom', 1300, ['Pack of 10' => 10]]]],
            ['Durex Play Lubricant', 'Sexual Wellness', [['50ml Tube', 9500, $each('Tube')]]],

            // Baby Care
            ['Pampers Baby Dry Tape Diapers', 'Baby Care', [['S', 420, ['Pack of 58' => 58]], ['M', 450, ['Pack of 50' => 50]], ['L', 480, ['Pack of 44' => 44]]]],
            ['Huggies Dry Pants', 'Baby Care', [['M', 470, ['Pack of 42' => 42]], ['L', 500, ['Pack of 36' => 36]], ['XL', 540, ['Pack of 32' => 32]]]],
            ['MamyPoko Pants Extra Dry', 'Baby Care', [['M', 440, ['Pack of 48' => 48]], ['L', 470, ['Pack of 42' => 42]], ['XL', 500, ['Pack of 36' => 36]], ['XXL', 520, ['Pack of 30' => 30]]]],
            ["Johnson's Baby Powder", 'Baby Care', [['100g Bottle', 2500, $each('Bottle')], ['200g Bottle', 4500, $each('Bottle')]]],
            ["Johnson's Baby Oil", 'Baby Care', [['125ml Bottle', 5500, $each('Bottle')]]],
            ["Johnson's Baby Shampoo", 'Baby Care', [['200ml Bottle', 6000, $each('Bottle')], ['500ml Bottle', 12500, $each('Bottle')]]],
            ["Johnson's Baby Lotion", 'Baby Care', [['200ml Bottle', 6500, $each('Bottle')]]],
            ['Sudocrem Antiseptic Healing Cream', 'Baby Care', [['60g Tub', 12000, $each('Tub')], ['125g Tub', 19000, $each('Tub')]]],
            ['Baby Wet Wipes Fragrance Free', 'Baby Care', [['80 Sheets', 3500, $each('Pack')]]],
            ['Similac Gain Plus Stage 3', 'Baby Care', [['400g Tin', 28000, $each('Tin')], ['900g Tin', 58000, $each('Tin')]]],
            ['Dumex Dugro Stage 3', 'Baby Care', [['400g Pack', 18000, $each('Pack')], ['800g Pack', 34000, $each('Pack')]]],
            ['Enfagrow A+ Stage 3', 'Baby Care', [['400g Tin', 32000, $each('Tin')]]],
            ['Lactogen 1 Infant Formula', 'Baby Care', [['400g Tin', 16000, $each('Tin')]]],
            ['Nestle Cerelac Wheat', 'Baby Care', [['250g Box', 6500, $each('Box')]]],
            ['Pigeon Feeding Bottle', 'Baby Care', [['150ml', 6500, $each('Bottle')], ['240ml', 7500, $each('Bottle')]]],
            ['Pigeon Silicone Teat', 'Baby Care', [['S', 2500, ['Pack of 2' => 2]], ['M', 2500, ['Pack of 2' => 2]]]],
            ['Woodward\'s Gripe Water', 'Baby Care', [['130ml Bottle', 3500, $each('Bottle')]]],

            // Personal Care
            ['Dettol Original Soap', 'Personal Care', [['100g Bar', 1800, ['Bar' => 1, 'Pack of 4' => 4]]]],
            ['Lifebuoy Total 10 Soap', 'Personal Care', [['70g Bar', 1200, ['Bar' => 1, 'Pack of 4' => 4]]]],
            ['Cetaphil Gentle Skin Cleanser', 'Personal Care', [['125ml Bottle', 18000, $each('Bottle')], ['250ml Bottle', 30000, $each('Bottle')]]],
            ['Cetaphil Moisturising Cream', 'Personal Care', [['100g Tub', 26000, $each('Tub')]]],
            ['Vaseline Pure Petroleum Jelly', 'Personal Care', [['50ml Jar', 3500, $each('Jar')], ['100ml Jar', 6000, $each('Jar')]]],
            ['Nivea Creme', 'Personal Care', [['60ml Tin', 4500, $each('Tin')], ['150ml Tin', 9500, $each('Tin')]]],
            ['Selsun Blue Anti-Dandruff Shampoo', 'Personal Care', [['120ml Bottle', 9500, $each('Bottle')]]],
            ['Biore UV Aqua Rich Sunscreen SPF50+', 'Personal Care', [['50g Tube', 16000, $each('Tube')]]],
            ['Himalaya Purifying Neem Face Wash', 'Personal Care', [['100ml Tube', 6500, $each('Tube')]]],
            ['Himalaya Lip Balm', 'Personal Care', [['4.5g', 3500, $each('Stick')]]],
            ['Lactacyd Feminine Wash', 'Personal Care', [['150ml Bottle', 8500, $each('Bottle')], ['250ml Bottle', 13000, $each('Bottle')]]],
            ['Whisper Ultra Sanitary Pads', 'Personal Care', [['Day 24cm', 250, ['Pack of 8' => 8, 'Pack of 16' => 16]], ['Night 28cm', 320, ['Pack of 7' => 7]]]],
            ['Sofy Body Fit Sanitary Pads', 'Personal Care', [['Day 23cm', 220, ['Pack of 10' => 10]]]],
            ['Carefree Pantyliners', 'Personal Care', [['Liner', 60, ['Pack of 20' => 20, 'Pack of 40' => 40]]]],
            ['Eucerin Urea Repair Lotion 5%', 'Personal Care', [['250ml Bottle', 32000, $each('Bottle')]]],

            // Oral Care
            ['Listerine Cool Mint Mouthwash', 'Oral Care', [['250ml Bottle', 6500, $each('Bottle')], ['750ml Bottle', 15000, $each('Bottle')]]],
            ['Colgate Total Toothpaste', 'Oral Care', [['150g Tube', 4500, $each('Tube')]]],
            ['Sensodyne Repair & Protect Toothpaste', 'Oral Care', [['100g Tube', 8500, $each('Tube')]]],
            ['Sensodyne Rapid Relief Toothpaste', 'Oral Care', [['100g Tube', 8500, $each('Tube')]]],
            ['Darlie Double Action Toothpaste', 'Oral Care', [['160g Tube', 3500, $each('Tube')]]],
            ['Oral-B Pro Soft Toothbrush', 'Oral Care', [['Brush', 2500, ['Single' => 1, 'Pack of 3' => 3]]]],
            ['Oral-B Essential Dental Floss', 'Oral Care', [['50m', 3500, $each('Pack')]]],
            ['Colgate Plax Mouthwash', 'Oral Care', [['250ml Bottle', 4500, $each('Bottle')]]],

            // Herbal & Traditional
            ['Tiger Balm White', 'Herbal & Traditional', [['10g Jar', 2000, $each('Jar')], ['19g Jar', 3500, $each('Jar')]]],
            ['Tiger Balm Red', 'Herbal & Traditional', [['10g Jar', 2000, $each('Jar')], ['19g Jar', 3500, $each('Jar')]]],
            ['Tiger Balm Neck & Shoulder Rub', 'Herbal & Traditional', [['50g Tube', 7500, $each('Tube')]]],
            ['Vicks VapoRub', 'Herbal & Traditional', [['25g Jar', 3000, $each('Jar')], ['50g Jar', 5500, $each('Jar')]]],
            ['Counterpain Analgesic Balm', 'Herbal & Traditional', [['60g Tube', 6500, $each('Tube')], ['120g Tube', 11500, $each('Tube')]]],
            ['Deep Heat Rub', 'Herbal & Traditional', [['35g Tube', 5500, $each('Tube')]]],
            ['Po Sum On Medicated Oil', 'Herbal & Traditional', [['30ml Bottle', 7500, $each('Bottle')]]],
            ['White Flower Embrocation', 'Herbal & Traditional', [['20ml Bottle', 6500, $each('Bottle')]]],
            ['Siang Pure Oil Formula I', 'Herbal & Traditional', [['3cc Bottle', 1500, ['Bottle' => 1, 'Box of 12' => 12]], ['7cc Bottle', 3000, ['Bottle' => 1]]]],
            ['Poy-Sian Mark II Inhaler', 'Herbal & Traditional', [['Inhaler', 1200, ['Single' => 1, 'Pack of 6' => 6]]]],
            ['Himalaya Liv.52 Tablet', 'Herbal & Traditional', [['Tablet', 60, ['Bottle of 100' => 100]]]],
            ['Himalaya Septilin Tablet', 'Herbal & Traditional', [['Tablet', 80, ['Bottle of 60' => 60]]]],
            ['Himalaya Cystone Tablet', 'Herbal & Traditional', [['Tablet', 80, ['Bottle of 60' => 60]]]],
            ['Ginkgo Biloba 120mg Capsule', 'Vitamins & Supplements', [['Capsule', 300, ['Bottle of 60' => 60]]]],
            ['Glucosamine 500mg Capsule', 'Vitamins & Supplements', [['Capsule', 250, ['Bottle of 60' => 60]]]],
            ['Turmeric Curcumin 500mg Capsule', 'Vitamins & Supplements', [['Capsule', 200, ['Bottle of 60' => 60]]]],
            ['Moringa Leaf 400mg Capsule', 'Herbal & Traditional', [['Capsule', 150, ['Bottle of 60' => 60]]]],
            ['Probiotic Sachet for Kids', 'Vitamins & Supplements', [['Sachet', 700, ['Single Sachet' => 1, 'Box of 10' => 10]]]],
        ];
    }
}
