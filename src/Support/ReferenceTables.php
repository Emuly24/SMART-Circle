<?php

declare(strict_types=1);

namespace SmartCircle\Support;

/**
 * Standard reference tables for notes ("[table: key]") and the board's Tables panel.
 * Values are the usual secondary-school textbook figures; where books differ slightly, a note says so.
 * Cells use the notes markup (H_2O, 10^{-3}, ->), so they render the same in notes and on the board.
 */
final class ReferenceTables
{
    /** @return array<string, array{title: string, subject: string, cols: list<string>, rows: list<list<string>>, note?: string}> */
    public static function all(): array
    {
        return [
            /* ---------------- Chemistry ---------------- */
            'elements20' => [
                'title' => 'The first 20 elements', 'subject' => 'chemistry',
                'cols' => ['Z', 'Symbol', 'Name', 'Relative atomic mass', 'Electron arrangement'],
                'rows' => [
                    ['1', 'H', 'Hydrogen', '1', '1'], ['2', 'He', 'Helium', '4', '2'], ['3', 'Li', 'Lithium', '7', '2,1'],
                    ['4', 'Be', 'Beryllium', '9', '2,2'], ['5', 'B', 'Boron', '11', '2,3'], ['6', 'C', 'Carbon', '12', '2,4'],
                    ['7', 'N', 'Nitrogen', '14', '2,5'], ['8', 'O', 'Oxygen', '16', '2,6'], ['9', 'F', 'Fluorine', '19', '2,7'],
                    ['10', 'Ne', 'Neon', '20', '2,8'], ['11', 'Na', 'Sodium', '23', '2,8,1'], ['12', 'Mg', 'Magnesium', '24', '2,8,2'],
                    ['13', 'Al', 'Aluminium', '27', '2,8,3'], ['14', 'Si', 'Silicon', '28', '2,8,4'], ['15', 'P', 'Phosphorus', '31', '2,8,5'],
                    ['16', 'S', 'Sulphur', '32', '2,8,6'], ['17', 'Cl', 'Chlorine', '35.5', '2,8,7'], ['18', 'Ar', 'Argon', '40', '2,8,8'],
                    ['19', 'K', 'Potassium', '39', '2,8,8,1'], ['20', 'Ca', 'Calcium', '40', '2,8,8,2'],
                ],
            ],
            'periodic' => [
                'title' => 'Periodic table (hydrogen to krypton)', 'subject' => 'chemistry',
                'cols' => ['Period', 'I', 'II', '', '', '', '', '', '', '', '', '', '', 'III', 'IV', 'V', 'VI', 'VII', '0'],
                'rows' => [
                    ['1', 'H', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'He'],
                    ['2', 'Li', 'Be', '', '', '', '', '', '', '', '', '', '', 'B', 'C', 'N', 'O', 'F', 'Ne'],
                    ['3', 'Na', 'Mg', '', '', '', '', '', '', '', '', '', '', 'Al', 'Si', 'P', 'S', 'Cl', 'Ar'],
                    ['4', 'K', 'Ca', 'Sc', 'Ti', 'V', 'Cr', 'Mn', 'Fe', 'Co', 'Ni', 'Cu', 'Zn', 'Ga', 'Ge', 'As', 'Se', 'Br', 'Kr'],
                ],
                'note' => 'Groups I–VII and 0 (noble gases); the transition metals sit between groups II and III.',
            ],
            'bondEnthalpy' => [
                'title' => 'Average bond enthalpies', 'subject' => 'chemistry',
                'cols' => ['Bond', 'kJ mol^{-1}', 'Bond', 'kJ mol^{-1}'],
                'rows' => [
                    ['H–H', '436', 'C=O', '805'], ['C–H', '413', 'O=O', '498'], ['C–C', '347', 'O–H', '464'],
                    ['C=C', '612', 'N≡N', '945'], ['C≡C', '838', 'N–H', '391'], ['C–O', '358', 'H–Cl', '432'],
                    ['C–Cl', '346', 'Cl–Cl', '243'], ['H–Br', '366', 'Br–Br', '193'],
                ],
                'note' => 'ΔH = bonds broken − bonds formed. Average values; books may differ by a few kJ.',
            ],
            'enthalpyCombustion' => [
                'title' => 'Standard enthalpies of combustion (ΔH_{c})', 'subject' => 'chemistry',
                'cols' => ['Substance', 'Formula', 'ΔH_{c} / kJ mol^{-1}'],
                'rows' => [
                    ['Hydrogen', 'H_2', '−286'], ['Carbon (graphite)', 'C', '−394'], ['Carbon monoxide', 'CO', '−283'],
                    ['Methane', 'CH_4', '−890'], ['Ethane', 'C_2H_6', '−1560'], ['Propane', 'C_3H_8', '−2220'],
                    ['Butane', 'C_4H_10', '−2877'], ['Methanol', 'CH_3OH', '−726'], ['Ethanol', 'C_2H_5OH', '−1367'],
                    ['Glucose', 'C_6H_12O_6', '−2803'],
                ],
                'note' => 'Combustion is exothermic, so every value is negative.',
            ],
            'enthalpyFormation' => [
                'title' => 'Standard enthalpies of formation (ΔH_{f})', 'subject' => 'chemistry',
                'cols' => ['Substance', 'ΔH_{f} / kJ mol^{-1}', 'Substance', 'ΔH_{f} / kJ mol^{-1}'],
                'rows' => [
                    ['H_2O(l)', '−286', 'NH_3(g)', '−46'], ['H_2O(g)', '−242', 'HCl(g)', '−92'], ['CO_2(g)', '−394', 'NaCl(s)', '−411'],
                    ['CO(g)', '−111', 'CaCO_3(s)', '−1207'], ['CH_4(g)', '−75', 'CaO(s)', '−635'], ['C_2H_4(g)', '+52', 'SO_2(g)', '−297'],
                    ['C_2H_5OH(l)', '−277', 'Fe_2O_3(s)', '−824'], ['C_6H_12O_6(s)', '−1273', 'Al_2O_3(s)', '−1676'],
                ],
                'note' => 'Elements in their standard states have ΔH_{f} = 0. ΔH = ΣΔH_{f}(products) − ΣΔH_{f}(reactants).',
            ],
            'enthalpyTypes' => [
                'title' => 'Types of enthalpy change', 'subject' => 'chemistry',
                'cols' => ['Name', 'What it means', 'Example'],
                'rows' => [
                    ['Combustion (ΔH_{c})', '1 mol burns completely in oxygen', 'CH_4 + 2O_2 -> CO_2 + 2H_2O'],
                    ['Formation (ΔH_{f})', '1 mol forms from its elements', 'C + O_2 -> CO_2'],
                    ['Neutralisation', '1 mol of water forms from acid + alkali (about −57 kJ for strong ones)', 'H^+ + OH^{-} -> H_2O'],
                    ['Solution', '1 mol dissolves in water', 'NH_4NO_3(s) -> NH_4^{+}(aq) + NO_3^{-}(aq)'],
                    ['Exothermic', 'heat given out; ΔH negative; surroundings warm up', 'burning, respiration'],
                    ['Endothermic', 'heat taken in; ΔH positive; surroundings cool', 'photosynthesis, thermal decomposition'],
                ],
            ],
            'reactivity' => [
                'title' => 'Reactivity series of metals', 'subject' => 'chemistry',
                'cols' => ['Metal', 'With water', 'With dilute acid', 'Extraction'],
                'rows' => [
                    ['Potassium (K)', 'violent with cold water', 'explosive', 'electrolysis'],
                    ['Sodium (Na)', 'vigorous with cold water', 'explosive', 'electrolysis'],
                    ['Calcium (Ca)', 'steady with cold water', 'vigorous', 'electrolysis'],
                    ['Magnesium (Mg)', 'with steam', 'fast', 'electrolysis'],
                    ['Aluminium (Al)', 'with steam (oxide layer slows it)', 'moderate', 'electrolysis'],
                    ['(Carbon)', '', '', ''],
                    ['Zinc (Zn)', 'with steam', 'moderate', 'reduction by carbon'],
                    ['Iron (Fe)', 'slowly with steam', 'slow', 'reduction by carbon'],
                    ['Lead (Pb)', 'none', 'very slow', 'reduction by carbon'],
                    ['(Hydrogen)', '', '', ''],
                    ['Copper (Cu)', 'none', 'none', 'found native / heating ores'],
                    ['Silver (Ag)', 'none', 'none', 'found native'],
                    ['Gold (Au)', 'none', 'none', 'found native'],
                ],
                'note' => 'A metal displaces any metal below it from a solution of its salt.',
            ],
            'ions' => [
                'title' => 'Common ions and their charges', 'subject' => 'chemistry',
                'cols' => ['Positive ion', 'Formula', 'Negative ion', 'Formula'],
                'rows' => [
                    ['Hydrogen', 'H^+', 'Chloride', 'Cl^{-}'], ['Sodium', 'Na^+', 'Bromide', 'Br^{-}'], ['Potassium', 'K^+', 'Iodide', 'I^{-}'],
                    ['Ammonium', 'NH_4^+', 'Hydroxide', 'OH^{-}'], ['Silver', 'Ag^+', 'Nitrate', 'NO_3^{-}'], ['Copper(II)', 'Cu^{2+}', 'Hydrogencarbonate', 'HCO_3^{-}'],
                    ['Magnesium', 'Mg^{2+}', 'Oxide', 'O^{2-}'], ['Calcium', 'Ca^{2+}', 'Sulphide', 'S^{2-}'], ['Zinc', 'Zn^{2+}', 'Sulphate', 'SO_4^{2-}'],
                    ['Iron(II)', 'Fe^{2+}', 'Carbonate', 'CO_3^{2-}'], ['Iron(III)', 'Fe^{3+}', 'Sulphite', 'SO_3^{2-}'], ['Aluminium', 'Al^{3+}', 'Phosphate', 'PO_4^{3-}'],
                ],
            ],
            'solubility' => [
                'title' => 'Solubility of salts in water', 'subject' => 'chemistry',
                'cols' => ['Salts', 'Soluble?', 'Exceptions'],
                'rows' => [
                    ['Sodium, potassium and ammonium salts', 'all soluble', 'none'], ['Nitrates', 'all soluble', 'none'],
                    ['Chlorides', 'soluble', 'silver chloride, lead(II) chloride'], ['Sulphates', 'soluble', 'barium, lead(II) and (slightly) calcium sulphate'],
                    ['Carbonates', 'insoluble', 'sodium, potassium, ammonium carbonate'], ['Hydroxides', 'insoluble', 'sodium, potassium, ammonium (calcium slightly)'],
                ],
            ],
            'indicators' => [
                'title' => 'Indicator colours', 'subject' => 'chemistry',
                'cols' => ['Indicator', 'In acid', 'In neutral', 'In alkali'],
                'rows' => [
                    ['Litmus', 'red', 'purple', 'blue'], ['Phenolphthalein', 'colourless', 'colourless', 'pink'],
                    ['Methyl orange', 'red', 'orange', 'yellow'], ['Universal indicator', 'red / orange (pH 1–6)', 'green (pH 7)', 'blue / purple (pH 8–14)'],
                ],
            ],
            'gasTests' => [
                'title' => 'Tests for gases', 'subject' => 'chemistry',
                'cols' => ['Gas', 'Test', 'Result'],
                'rows' => [
                    ['Hydrogen (H_2)', 'lighted splint', 'burns with a squeaky pop'], ['Oxygen (O_2)', 'glowing splint', 'relights'],
                    ['Carbon dioxide (CO_2)', 'bubble through limewater', 'limewater turns milky'], ['Ammonia (NH_3)', 'damp red litmus', 'turns blue'],
                    ['Chlorine (Cl_2)', 'damp blue litmus', 'turns red, then bleached'], ['Sulphur dioxide (SO_2)', 'acidified potassium dichromate paper', 'orange to green'],
                    ['Water vapour', 'anhydrous copper(II) sulphate', 'white to blue'],
                ],
            ],
            'equations' => [
                'title' => 'Common balanced equations', 'subject' => 'chemistry',
                'cols' => ['Reaction', 'Equation'],
                'rows' => [
                    ['Photosynthesis', '6CO_2 + 6H_2O -> C_6H_12O_6 + 6O_2'], ['Respiration', 'C_6H_12O_6 + 6O_2 -> 6CO_2 + 6H_2O'],
                    ['Burning methane', 'CH_4 + 2O_2 -> CO_2 + 2H_2O'], ['Burning hydrogen', '2H_2 + O_2 -> 2H_2O'],
                    ['Neutralisation', 'HCl + NaOH -> NaCl + H_2O'], ['Acid + metal', 'Mg + 2HCl -> MgCl_2 + H_2'],
                    ['Acid + carbonate', 'CaCO_3 + 2HCl -> CaCl_2 + H_2O + CO_2'], ['Thermal decomposition', 'CaCO_3 -> CaO + CO_2'],
                    ['Haber process', 'N_2 + 3H_2 <=> 2NH_3'], ['Contact process', '2SO_2 + O_2 <=> 2SO_3'],
                    ['Rusting', '4Fe + 3O_2 + 6H_2O -> 4Fe(OH)_3'], ['Fermentation', 'C_6H_12O_6 -> 2C_2H_5OH + 2CO_2'],
                    ['Limewater test', 'Ca(OH)_2 + CO_2 -> CaCO_3 + H_2O'], ['Displacement', 'Zn + CuSO_4 -> ZnSO_4 + Cu'],
                ],
            ],
            'homologous' => [
                'title' => 'Homologous series (C_{1} to C_{4})', 'subject' => 'chemistry',
                'cols' => ['Carbons', 'Alkane (C_{n}H_{2n+2})', 'Alkene (C_{n}H_{2n})', 'Alcohol (C_{n}H_{2n+1}OH)'],
                'rows' => [
                    ['1', 'methane CH_4', '—', 'methanol CH_3OH'], ['2', 'ethane C_2H_6', 'ethene C_2H_4', 'ethanol C_2H_5OH'],
                    ['3', 'propane C_3H_8', 'propene C_3H_6', 'propanol C_3H_7OH'], ['4', 'butane C_4H_10', 'butene C_4H_8', 'butanol C_4H_9OH'],
                ],
                'note' => 'Functional groups: alkene C=C, alcohol –OH, carboxylic acid –COOH.',
            ],

            /* ---------------- Physics ---------------- */
            'siUnits' => [
                'title' => 'SI base units', 'subject' => 'physics',
                'cols' => ['Quantity', 'Unit', 'Symbol'],
                'rows' => [['Length', 'metre', 'm'], ['Mass', 'kilogram', 'kg'], ['Time', 'second', 's'], ['Electric current', 'ampere', 'A'], ['Temperature', 'kelvin', 'K'], ['Amount of substance', 'mole', 'mol'], ['Luminous intensity', 'candela', 'cd']],
            ],
            'prefixes' => [
                'title' => 'Unit prefixes', 'subject' => 'physics',
                'cols' => ['Prefix', 'Symbol', 'Multiply by'],
                'rows' => [['giga', 'G', '10^9'], ['mega', 'M', '10^6'], ['kilo', 'k', '10^3'], ['centi', 'c', '10^{-2}'], ['milli', 'm', '10^{-3}'], ['micro', 'µ', '10^{-6}'], ['nano', 'n', '10^{-9}']],
            ],
            'physicsFormulas' => [
                'title' => 'Physics formulas', 'subject' => 'physics',
                'cols' => ['Quantity', 'Formula', 'Unit'],
                'rows' => [
                    ['Speed', 'v = d / t', 'm/s'], ['Acceleration', 'a = (v − u) / t', 'm/s^2'], ['Equations of motion', 'v = u + at;  s = ut + ½at^2;  v^2 = u^2 + 2as', ''],
                    ['Force', 'F = ma', 'N'], ['Weight', 'W = mg', 'N'], ['Density', 'ρ = m / V', 'kg/m^3'], ['Pressure', 'P = F / A;  P = ρgh', 'Pa'],
                    ['Work', 'W = Fd', 'J'], ['Power', 'P = W / t', 'W'], ['Kinetic energy', 'E_{k} = ½mv^2', 'J'], ['Potential energy', 'E_{p} = mgh', 'J'],
                    ['Moment', 'moment = F × d', 'N m'], ['Heat', 'Q = mcΔθ;  Q = mL', 'J'], ['Ohm’s law', 'V = IR', 'V'], ['Electrical power', 'P = VI = I^2R', 'W'],
                    ['Charge', 'Q = It', 'C'], ['Waves', 'v = fλ', 'm/s'], ['Refractive index', 'n = sin i / sin r', ''], ['Transformer', 'V_{s} / V_{p} = N_{s} / N_{p}', ''],
                ],
            ],
            'constants' => [
                'title' => 'Useful constants', 'subject' => 'physics',
                'cols' => ['Constant', 'Value'],
                'rows' => [
                    ['Acceleration due to gravity (g)', '9.8 m/s^2 (10 N/kg in many exam questions)'], ['Speed of light in a vacuum (c)', '3.0 × 10^8 m/s'],
                    ['Speed of sound in air', 'about 330–340 m/s'], ['Specific heat capacity of water', '4200 J/(kg °C)'], ['Specific latent heat of fusion of ice', '3.34 × 10^5 J/kg'],
                    ['Specific latent heat of vaporisation of water', '2.26 × 10^6 J/kg'], ['Density of water', '1000 kg/m^3'], ['Atmospheric pressure', 'about 1.0 × 10^5 Pa'],
                ],
            ],
            'densities' => [
                'title' => 'Densities of some materials', 'subject' => 'physics',
                'cols' => ['Material', 'Density / kg m^{-3}'],
                'rows' => [['Air', '1.3'], ['Wood (pine)', 'about 500'], ['Ice', '920'], ['Water', '1000'], ['Sea water', '1030'], ['Aluminium', '2700'], ['Glass', '2500'], ['Iron', '7900'], ['Copper', '8900'], ['Lead', '11 300'], ['Mercury', '13 600'], ['Gold', '19 300']],
            ],
            'heatCapacity' => [
                'title' => 'Specific heat capacities', 'subject' => 'physics',
                'cols' => ['Material', 'c / J kg^{-1} °C^{-1}'],
                'rows' => [['Water', '4200'], ['Ice', '2100'], ['Aluminium', '900'], ['Glass', '670'], ['Iron', '450'], ['Copper', '390'], ['Lead', '130']],
            ],
            'emSpectrum' => [
                'title' => 'Electromagnetic spectrum', 'subject' => 'physics',
                'cols' => ['Wave', 'Typical wavelength', 'Use'],
                'rows' => [['Radio waves', '1 m – 1 km', 'radio, TV'], ['Microwaves', '1 mm – 30 cm', 'cooking, mobile phones'], ['Infrared', '700 nm – 1 mm', 'heaters, remote controls'], ['Visible light', '400 – 700 nm', 'seeing, photography'], ['Ultraviolet', '10 – 400 nm', 'sterilising, detecting forged notes'], ['X-rays', '0.01 – 10 nm', 'seeing bones'], ['Gamma rays', 'below 0.01 nm', 'killing cancer cells, sterilising']],
                'note' => 'All travel at 3 × 10^8 m/s in a vacuum. Frequency rises as wavelength falls.',
            ],

            /* ---------------- Biology ---------------- */
            'foodTests' => [
                'title' => 'Food tests', 'subject' => 'biology',
                'cols' => ['Food', 'Reagent', 'Positive result'],
                'rows' => [['Starch', 'iodine solution', 'brown → blue-black'], ['Reducing sugar (glucose)', 'Benedict’s solution, heat', 'blue → green, yellow, orange or brick red'], ['Protein', 'Biuret (sodium hydroxide + copper sulphate)', 'blue → purple'], ['Fat / oil', 'ethanol, then water (emulsion test)', 'cloudy white emulsion'], ['Fat / oil', 'rub on paper', 'translucent spot'], ['Vitamin C', 'DCPIP', 'blue → colourless']],
            ],
            'nutrients' => [
                'title' => 'Nutrients, sources and deficiency', 'subject' => 'biology',
                'cols' => ['Nutrient', 'Good sources', 'Needed for', 'Deficiency'],
                'rows' => [
                    ['Carbohydrate', 'nsima (maize), rice, cassava', 'energy', 'tiredness, marasmus'], ['Protein', 'beans, fish (usipa, chambo), eggs, meat', 'growth and repair', 'kwashiorkor'],
                    ['Fat', 'groundnuts, cooking oil, milk', 'energy store, insulation', 'weight loss'], ['Vitamin A', 'carrots, mangoes, pumpkin leaves', 'healthy eyes', 'night blindness'],
                    ['Vitamin C', 'oranges, guavas, tomatoes', 'healthy gums and skin', 'scurvy'], ['Vitamin D', 'sunlight, eggs, fish', 'strong bones', 'rickets'],
                    ['Iron', 'green leafy vegetables, liver, beans', 'haemoglobin', 'anaemia'], ['Calcium', 'milk, usipa, green vegetables', 'bones and teeth', 'rickets, weak teeth'],
                    ['Iodine', 'iodised salt, sea food', 'thyroxine', 'goitre'], ['Fibre', 'whole maize, vegetables, fruit', 'moving food through the gut', 'constipation'],
                ],
            ],
            'cells' => [
                'title' => 'Plant and animal cells compared', 'subject' => 'biology',
                'cols' => ['Part', 'Plant cell', 'Animal cell'],
                'rows' => [['Cell membrane', 'yes', 'yes'], ['Cytoplasm', 'yes', 'yes'], ['Nucleus', 'yes', 'yes'], ['Mitochondria', 'yes', 'yes'], ['Cell wall (cellulose)', 'yes', 'no'], ['Chloroplasts', 'yes (in green parts)', 'no'], ['Large permanent vacuole', 'yes', 'no (small temporary ones)'], ['Shape', 'regular', 'irregular']],
            ],
            'vessels' => [
                'title' => 'Blood vessels compared', 'subject' => 'biology',
                'cols' => ['', 'Artery', 'Vein', 'Capillary'],
                'rows' => [['Carries blood', 'away from the heart', 'to the heart', 'between arteries and veins'], ['Wall', 'thick, muscular, elastic', 'thinner', 'one cell thick'], ['Pressure', 'high', 'low', 'falling'], ['Valves', 'no', 'yes', 'no'], ['Blood (mostly)', 'oxygenated', 'deoxygenated', 'exchanges substances']],
            ],
            'enzymes' => [
                'title' => 'Digestive enzymes', 'subject' => 'biology',
                'cols' => ['Enzyme', 'Made in', 'Acts on', 'Product'],
                'rows' => [['Salivary amylase', 'salivary glands', 'starch', 'maltose'], ['Pepsin', 'stomach', 'protein', 'polypeptides'], ['Pancreatic amylase', 'pancreas', 'starch', 'maltose'], ['Trypsin', 'pancreas', 'protein', 'polypeptides'], ['Lipase', 'pancreas', 'fats', 'fatty acids and glycerol'], ['Maltase', 'small intestine', 'maltose', 'glucose']],
            ],
            'kingdoms' => [
                'title' => 'The five kingdoms', 'subject' => 'biology',
                'cols' => ['Kingdom', 'Features', 'Examples'],
                'rows' => [['Monera (Prokaryotae)', 'one cell, no nucleus', 'bacteria'], ['Protoctista', 'mostly one cell, with a nucleus', 'amoeba, plasmodium'], ['Fungi', 'cell walls of chitin, feed by absorbing', 'mushrooms, yeast, mould'], ['Plantae', 'cellulose walls, chlorophyll, photosynthesis', 'maize, mango tree, moss'], ['Animalia', 'many cells, no cell wall, feed on others', 'goat, chambo fish, insects']],
            ],

            /* ---------------- Mathematics ---------------- */
            'trigValues' => [
                'title' => 'Trigonometric ratios of special angles', 'subject' => 'mathematics',
                'cols' => ['', '0°', '30°', '45°', '60°', '90°'],
                'rows' => [['sin', '0', '½', '1/√2', '√3/2', '1'], ['cos', '1', '√3/2', '1/√2', '½', '0'], ['tan', '0', '1/√3', '1', '√3', 'undefined']],
                'note' => 'SOH CAH TOA: sin = opposite/hypotenuse, cos = adjacent/hypotenuse, tan = opposite/adjacent.',
            ],
            'areaFormulas' => [
                'title' => 'Area and perimeter', 'subject' => 'mathematics',
                'cols' => ['Shape', 'Area', 'Perimeter'],
                'rows' => [['Rectangle', 'l × w', '2(l + w)'], ['Square', 's^2', '4s'], ['Triangle', '½ × b × h', 'a + b + c'], ['Parallelogram', 'b × h', '2(a + b)'], ['Trapezium', '½(a + b)h', 'sum of sides'], ['Circle', 'πr^2', '2πr (circumference)'], ['Sector', '(θ/360) × πr^2', '2r + (θ/360) × 2πr']],
            ],
            'volumeFormulas' => [
                'title' => 'Volume and surface area', 'subject' => 'mathematics',
                'cols' => ['Solid', 'Volume', 'Surface area'],
                'rows' => [['Cuboid', 'l × w × h', '2(lw + lh + wh)'], ['Cube', 's^3', '6s^2'], ['Cylinder', 'πr^2h', '2πr^2 + 2πrh'], ['Cone', '⅓πr^2h', 'πr^2 + πrl'], ['Sphere', '(4/3)πr^3', '4πr^2'], ['Pyramid', '⅓ × base area × h', 'base + triangles'], ['Prism', 'cross-section area × length', 'sum of faces']],
            ],
            'indices' => [
                'title' => 'Laws of indices and logarithms', 'subject' => 'mathematics',
                'cols' => ['Indices', 'Logarithms'],
                'rows' => [['a^m × a^n = a^{m+n}', 'log(xy) = log x + log y'], ['a^m ÷ a^n = a^{m−n}', 'log(x/y) = log x − log y'], ['(a^m)^n = a^{mn}', 'log x^n = n log x'], ['a^0 = 1', 'log_a a = 1'], ['a^{-n} = 1/a^n', 'log_a 1 = 0'], ['a^{1/n} = ⁿ√a', 'if a^x = y then log_a y = x']],
            ],
            'algebra' => [
                'title' => 'Algebra: identities and the quadratic formula', 'subject' => 'mathematics',
                'cols' => ['Rule', 'Formula'],
                'rows' => [['Square of a sum', '(a + b)^2 = a^2 + 2ab + b^2'], ['Square of a difference', '(a − b)^2 = a^2 − 2ab + b^2'], ['Difference of two squares', 'a^2 − b^2 = (a + b)(a − b)'], ['Quadratic formula', 'x = (−b ± √(b^2 − 4ac)) / 2a'], ['Gradient', 'm = (y_2 − y_1) / (x_2 − x_1)'], ['Straight line', 'y = mx + c'], ['Pythagoras', 'a^2 + b^2 = c^2']],
            ],
            'angleFacts' => [
                'title' => 'Angle facts', 'subject' => 'mathematics',
                'cols' => ['Fact', 'Sum or rule'],
                'rows' => [['Angles on a straight line', '180°'], ['Angles at a point', '360°'], ['Vertically opposite angles', 'equal'], ['Angles in a triangle', '180°'], ['Angles in a quadrilateral', '360°'], ['Interior angles of an n-sided polygon', '(n − 2) × 180°'], ['Exterior angles of any polygon', '360°'], ['Parallel lines', 'alternate angles equal; corresponding equal; co-interior add to 180°']],
            ],
            'circleTheorems' => [
                'title' => 'Circle theorems', 'subject' => 'mathematics',
                'cols' => ['Theorem'],
                'rows' => [['The angle at the centre is twice the angle at the circumference.'], ['The angle in a semicircle is 90°.'], ['Angles in the same segment are equal.'], ['Opposite angles of a cyclic quadrilateral add up to 180°.'], ['A tangent is perpendicular to the radius at the point of contact.'], ['Tangents from an outside point are equal in length.'], ['Alternate segment theorem: the angle between a tangent and a chord equals the angle in the alternate segment.']],
            ],
            'units' => [
                'title' => 'Metric conversions', 'subject' => 'mathematics',
                'cols' => ['Length', 'Mass', 'Capacity / area'],
                'rows' => [['10 mm = 1 cm', '1000 mg = 1 g', '1000 ml = 1 l'], ['100 cm = 1 m', '1000 g = 1 kg', '1 cm^3 = 1 ml'], ['1000 m = 1 km', '1000 kg = 1 tonne', '10 000 m^2 = 1 hectare']],
            ],

            /* ---------------- English grammar ---------------- */
            'tenses' => [
                'title' => 'The twelve tenses', 'subject' => 'english',
                'cols' => ['', 'Past', 'Present', 'Future'],
                'rows' => [['Simple', 'I walked.', 'I walk.', 'I will walk.'], ['Continuous', 'I was walking.', 'I am walking.', 'I will be walking.'], ['Perfect', 'I had walked.', 'I have walked.', 'I will have walked.'], ['Perfect continuous', 'I had been walking.', 'I have been walking.', 'I will have been walking.']],
            ],
            'irregularVerbs' => [
                'title' => 'Common irregular verbs', 'subject' => 'english',
                'cols' => ['Base', 'Past simple', 'Past participle', 'Base', 'Past simple', 'Past participle'],
                'rows' => [['be', 'was / were', 'been', 'go', 'went', 'gone'], ['begin', 'began', 'begun', 'have', 'had', 'had'], ['break', 'broke', 'broken', 'know', 'knew', 'known'], ['bring', 'brought', 'brought', 'make', 'made', 'made'], ['buy', 'bought', 'bought', 'run', 'ran', 'run'], ['choose', 'chose', 'chosen', 'see', 'saw', 'seen'], ['come', 'came', 'come', 'speak', 'spoke', 'spoken'], ['do', 'did', 'done', 'take', 'took', 'taken'], ['drink', 'drank', 'drunk', 'teach', 'taught', 'taught'], ['eat', 'ate', 'eaten', 'think', 'thought', 'thought'], ['give', 'gave', 'given', 'write', 'wrote', 'written']],
            ],
            'partsOfSpeech' => [
                'title' => 'Parts of speech', 'subject' => 'english',
                'cols' => ['Part', 'What it does', 'Examples'],
                'rows' => [['Noun', 'names a person, place, thing or idea', 'Chiyembekezo, Zomba, book, kindness'], ['Pronoun', 'takes the place of a noun', 'he, she, it, they, who'], ['Verb', 'shows an action or state', 'run, is, became'], ['Adjective', 'describes a noun', 'tall, blue, three'], ['Adverb', 'describes a verb, adjective or adverb', 'quickly, very, often'], ['Preposition', 'shows position or relationship', 'in, on, under, after'], ['Conjunction', 'joins words or clauses', 'and, but, because, although'], ['Determiner / article', 'introduces a noun', 'a, an, the, this, some'], ['Interjection', 'shows feeling', 'Oh! Wow! Ouch!']],
            ],
            'punctuation' => [
                'title' => 'Punctuation marks', 'subject' => 'english',
                'cols' => ['Mark', 'Name', 'Use'],
                'rows' => [['.', 'full stop', 'ends a statement'], [',', 'comma', 'separates items, clauses, or after an introductory word'], ['?', 'question mark', 'ends a direct question'], ['!', 'exclamation mark', 'shows strong feeling'], [':', 'colon', 'introduces a list or explanation'], [';', 'semicolon', 'joins two related sentences'], ['’', 'apostrophe', 'possession (Tamanda’s pen) or a missing letter (don’t)'], ['“ ”', 'quotation marks', 'direct speech or a quotation'], ['( )', 'brackets', 'extra information']],
            ],
            'reportedSpeech' => [
                'title' => 'Direct to reported speech', 'subject' => 'english',
                'cols' => ['Direct speech', 'Reported speech'],
                'rows' => [['present simple: “I am tired.”', 'past simple: she said (that) she was tired.'], ['present continuous: “I am reading.”', 'past continuous: he said he was reading.'], ['past simple: “I went.”', 'past perfect: she said she had gone.'], ['will: “I will come.”', 'would: he said he would come.'], ['can: “I can swim.”', 'could: she said she could swim.'], ['now / today / here', 'then / that day / there'], ['tomorrow / yesterday', 'the next day / the day before']],
            ],
            'activePassive' => [
                'title' => 'Active and passive voice', 'subject' => 'english',
                'cols' => ['Tense', 'Active', 'Passive'],
                'rows' => [['Present simple', 'Thoko cooks the food.', 'The food is cooked by Thoko.'], ['Past simple', 'Thoko cooked the food.', 'The food was cooked by Thoko.'], ['Present perfect', 'Thoko has cooked the food.', 'The food has been cooked by Thoko.'], ['Future', 'Thoko will cook the food.', 'The food will be cooked by Thoko.'], ['Continuous', 'Thoko is cooking the food.', 'The food is being cooked by Thoko.']],
            ],
        ];
    }

    /** @return array{title: string, subject: string, cols: list<string>, rows: list<list<string>>, note?: string}|null */
    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }

    /** Replaces "[table: key]" lines with the table written out as "## Title" and "| a | b |" rows. Pure. */
    public static function expand(string $text): string
    {
        return (string) preg_replace_callback('/^\s*\[table:\s*([A-Za-z0-9]+)\s*\]\s*$/m', static function (array $m): string {
            $t = self::get($m[1]);
            if ($t === null) {
                return $m[0];
            }
            $row = static fn (array $cells): string => '| ' . implode(' | ', array_map(static fn (string $c): string => $c === '' ? ' ' : $c, $cells)) . ' |';

            return "\n## " . $t['title'] . "\n" . $row($t['cols']) . "\n" . implode("\n", array_map($row, $t['rows'])) . "\n" . (isset($t['note']) ? "\n" . $t['note'] . "\n" : '');
        }, $text);
    }

    /** Titles by subject, for menus. @return array<string, array<string, string>> subject => [key => title] */
    public static function menu(): array
    {
        $out = [];
        foreach (self::all() as $key => $t) {
            $out[$t['subject']][$key] = $t['title'];
        }

        return $out;
    }
}
