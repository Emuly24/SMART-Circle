-- SMART Circle v2 — starting data: the first intake and a DRAFT entry quiz.
-- The tutor should review and edit these questions before advertising.
-- Each form: 2 questions per subject (humanities applicants skip Physics/Chemistry).

INSERT INTO intakes (name, opens_at, closes_at, seats_per_group, is_active)
VALUES ('First intake', NOW(), NULL, 3, 1);

INSERT INTO entry_quiz_questions (form, subject, question, option_a, option_b, option_c, option_d, correct, explanation, sort_order) VALUES
-- ---------------- Form 1 ----------------
(1, 'mathematics', 'What is 3/4 of 20?', '12', '15', '16', '5', 'b', '20 divided by 4 is 5, and 5 times 3 is 15.', 1),
(1, 'mathematics', 'Simplify 5x + 3x - 2x.', '6x', '10x', '8x', '6', 'a', 'Add and subtract the like terms: 5 + 3 - 2 = 6.', 2),
(1, 'english', 'Choose the correct sentence.', 'She go to school every day.', 'She goes to school every day.', 'She going to school every day.', 'She gone to school every day.', 'b', 'With he/she/it in the present simple tense, the verb takes -s.', 3),
(1, 'english', 'Which word is a noun in: "The tall boy kicked the ball"?', 'tall', 'kicked', 'ball', 'the', 'c', 'A noun names a thing; "ball" (and "boy") are nouns.', 4),
(1, 'biology', 'Which part of a plant cell makes food using light?', 'Nucleus', 'Chloroplast', 'Cell wall', 'Vacuole', 'b', 'Chloroplasts carry out photosynthesis.', 5),
(1, 'biology', 'Which of these is NOT a characteristic of living things?', 'Growth', 'Reproduction', 'Excretion', 'Rusting', 'd', 'Rusting is a chemical change in metals, not a life process.', 6),
(1, 'physics', 'What is the SI unit of length?', 'Kilogram', 'Metre', 'Second', 'Newton', 'b', 'Length is measured in metres (m).', 7),
(1, 'physics', 'A stone falls to the ground because of', 'friction', 'magnetism', 'gravity', 'air resistance', 'c', 'Gravity pulls objects towards the Earth.', 8),
(1, 'chemistry', 'Water boiling and turning into steam is an example of', 'a chemical change', 'a physical change', 'burning', 'rusting', 'b', 'Changing state does not make a new substance.', 9),
(1, 'chemistry', 'Which of these is a mixture?', 'Pure water', 'Oxygen', 'Salt water', 'Gold', 'c', 'Salt water is salt and water mixed together, not chemically joined.', 10),
-- ---------------- Form 2 ----------------
(2, 'mathematics', 'Solve: 2x + 5 = 17', 'x = 6', 'x = 11', 'x = 8.5', 'x = 12', 'a', 'Subtract 5 to get 2x = 12, then divide by 2.', 1),
(2, 'mathematics', 'What is the area of a rectangle 8 cm long and 5 cm wide?', '13 cm²', '26 cm²', '40 cm²', '45 cm²', 'c', 'Area = length × width = 8 × 5 = 40 cm².', 2),
(2, 'english', 'Choose the correct past tense: "Yesterday we ___ maize at the market."', 'buy', 'buyed', 'bought', 'buying', 'c', '"Buy" is irregular: buy, bought, bought.', 3),
(2, 'english', 'Which sentence uses the apostrophe correctly?', 'The dogs bone is big.', 'The dog''s bone is big.', 'The dogs bone''s is big.', 'The dog''s'' bone is big.', 'b', 'One dog owning the bone: dog''s.', 4),
(2, 'biology', 'Which blood vessels carry blood away from the heart?', 'Veins', 'Arteries', 'Capillaries', 'Valves', 'b', 'Arteries carry blood away from the heart.', 5),
(2, 'biology', 'Malaria is spread by', 'houseflies', 'female Anopheles mosquitoes', 'drinking dirty water', 'tsetse flies', 'b', 'The female Anopheles mosquito carries the malaria parasite.', 6),
(2, 'physics', 'Which material is a good conductor of electricity?', 'Plastic', 'Rubber', 'Copper', 'Dry wood', 'c', 'Metals such as copper conduct electricity well.', 7),
(2, 'physics', 'Speed is calculated as', 'distance × time', 'distance ÷ time', 'time ÷ distance', 'mass × distance', 'b', 'Speed = distance travelled ÷ time taken.', 8),
(2, 'chemistry', 'What is the chemical symbol for sodium?', 'S', 'So', 'Na', 'N', 'c', 'Sodium''s symbol comes from its Latin name, natrium.', 9),
(2, 'chemistry', 'A solution with pH 2 is', 'strongly acidic', 'neutral', 'weakly alkaline', 'strongly alkaline', 'a', 'pH below 7 is acidic, and the lower the number the stronger the acid.', 10),
-- ---------------- Form 3 ----------------
(3, 'mathematics', 'Factorise completely: x² - 9', '(x - 3)²', '(x + 3)(x - 3)', 'x(x - 9)', '(x + 9)(x - 1)', 'b', 'Difference of two squares: a² - b² = (a + b)(a - b).', 1),
(3, 'mathematics', 'The gradient of the line y = 3x - 4 is', '-4', '3', '4', '-3', 'b', 'In y = mx + c, m is the gradient.', 2),
(3, 'english', 'Change to reported speech: She said, "I am tired."', 'She said that she is tired.', 'She said that I am tired.', 'She said that she was tired.', 'She says that she was tired.', 'c', 'In reported speech, "am" moves back to "was" and "I" becomes "she".', 3),
(3, 'english', 'Choose the correct verb: "Neither the teacher nor the students ___ ready."', 'is', 'was', 'are', 'be', 'c', 'With neither...nor, the verb agrees with the nearer subject ("students").', 4),
(3, 'biology', 'In which organelle does aerobic respiration mainly take place?', 'Ribosome', 'Mitochondrion', 'Chloroplast', 'Nucleus', 'b', 'Mitochondria release energy from food in aerobic respiration.', 5),
(3, 'biology', 'The process by which water moves from a dilute to a concentrated solution through a partially permeable membrane is', 'diffusion', 'osmosis', 'transpiration', 'active transport', 'b', 'This is the definition of osmosis.', 6),
(3, 'physics', 'A current of 2 A flows through a 6 Ω resistor. What is the voltage across it?', '3 V', '8 V', '12 V', '0.33 V', 'c', 'V = IR = 2 × 6 = 12 V.', 7),
(3, 'physics', 'A force of 10 N acts on a 2 kg mass. What is its acceleration?', '20 m/s²', '5 m/s²', '12 m/s²', '0.2 m/s²', 'b', 'a = F ÷ m = 10 ÷ 2 = 5 m/s².', 8),
(3, 'chemistry', 'How many moles are in 36 g of water (H₂O)? (H = 1, O = 16)', '1 mole', '2 moles', '18 moles', '0.5 moles', 'b', 'Molar mass of water = 18 g/mol, so 36 ÷ 18 = 2 moles.', 9),
(3, 'chemistry', 'Which type of bond forms when atoms share electrons?', 'Ionic', 'Covalent', 'Metallic', 'Hydrogen', 'b', 'Sharing electron pairs is covalent bonding.', 10),
-- ---------------- Form 4 ----------------
(4, 'mathematics', 'Solve: x² - 5x + 6 = 0', 'x = 2 or x = 3', 'x = -2 or x = -3', 'x = 1 or x = 6', 'x = -1 or x = 6', 'a', '(x - 2)(x - 3) = 0, so x = 2 or 3.', 1),
(4, 'mathematics', 'A fair die is thrown once. What is the probability of getting a number greater than 4?', '1/6', '1/3', '1/2', '2/3', 'b', 'Numbers greater than 4 are 5 and 6: 2 out of 6 = 1/3.', 2),
(4, 'english', 'Choose the correct form: "If I ___ you, I would study harder."', 'am', 'was being', 'were', 'will be', 'c', 'The second conditional uses "were" for imagined situations.', 3),
(4, 'english', 'Which sentence is in the passive voice?', 'The farmer planted the maize.', 'The maize was planted by the farmer.', 'The farmer is planting maize.', 'Plant the maize now.', 'b', 'In the passive, the object (maize) becomes the subject.', 4),
(4, 'biology', 'Which hormone lowers blood sugar levels?', 'Adrenaline', 'Insulin', 'Glucagon', 'Thyroxine', 'b', 'Insulin from the pancreas lowers blood glucose.', 5),
(4, 'biology', 'In a cross between two heterozygous tall pea plants (Tt × Tt), what ratio of tall to short is expected?', '1 : 1', '3 : 1', '1 : 2 : 1', 'All tall', 'b', 'TT, Tt, Tt are tall and tt is short: 3 tall to 1 short.', 6),
(4, 'physics', 'Which of these is a longitudinal wave?', 'Light', 'Radio waves', 'Sound', 'Water ripples', 'c', 'In sound waves the particles vibrate along the direction of travel.', 7),
(4, 'physics', 'A 60 W bulb is used for 5 hours. How much energy does it use?', '12 Wh', '300 Wh', '65 Wh', '0.3 Wh', 'b', 'Energy = power × time = 60 W × 5 h = 300 Wh (0.3 kWh).', 8),
(4, 'chemistry', 'What is the name of the alkane with the formula C₂H₆?', 'Methane', 'Ethene', 'Ethane', 'Propane', 'c', 'Two carbons with single bonds only: ethane.', 9),
(4, 'chemistry', 'During electrolysis, positive ions move towards the', 'anode', 'cathode', 'electrolyte', 'salt bridge', 'b', 'Opposites attract: positive ions (cations) go to the negative cathode.', 10);
