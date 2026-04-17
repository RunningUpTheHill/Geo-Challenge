-- This must match the database selected in db/schema.sql and db_config.php.
USE geo_challenge;

INSERT INTO questions (category, difficulty, question_text, image_url, options, correct_index) VALUES

-- ── CAPITALS ──────────────────────────────────────────────────────────────
('capitals', 'easy',   'What is the capital of France?',
 NULL, '["Lyon","Paris","Bordeaux","Marseille"]', 1),

('capitals', 'easy',   'What is the capital of Australia?',
 NULL, '["Sydney","Melbourne","Canberra","Brisbane"]', 2),

('capitals', 'easy',   'What is the capital of Brazil?',
 NULL, '["São Paulo","Rio de Janeiro","Salvador","Brasília"]', 3),

('capitals', 'easy',   'What is the capital of Canada?',
 NULL, '["Ottawa","Toronto","Vancouver","Montreal"]', 0),

('capitals', 'easy',   'What is the capital of Japan?',
 NULL, '["Osaka","Kyoto","Hiroshima","Tokyo"]', 3),

('capitals', 'easy',   'What is the capital of Germany?',
 NULL, '["Munich","Hamburg","Frankfurt","Berlin"]', 3),

('capitals', 'easy',   'What is the capital of Mexico?',
 NULL, '["Guadalajara","Mexico City","Monterrey","Puebla"]', 1),

('capitals', 'medium', 'What is the (administrative) capital of South Africa?',
 NULL, '["Cape Town","Johannesburg","Pretoria","Durban"]', 2),

('capitals', 'medium', 'What is the capital of Argentina?',
 NULL, '["Córdoba","Rosario","Mendoza","Buenos Aires"]', 3),

('capitals', 'medium', 'What is the capital of Nigeria?',
 NULL, '["Lagos","Kano","Ibadan","Abuja"]', 3),

('capitals', 'medium', 'What is the capital of Pakistan?',
 NULL, '["Karachi","Lahore","Islamabad","Peshawar"]', 2),

('capitals', 'medium', 'What is the capital of Indonesia?',
 NULL, '["Surabaya","Bandung","Jakarta","Medan"]', 2),

('capitals', 'hard',   'What is the capital of Kazakhstan?',
 NULL, '["Almaty","Shymkent","Astana","Aktobe"]', 2),

('capitals', 'hard',   'What is the capital of Myanmar?',
 NULL, '["Yangon","Mandalay","Bago","Naypyidaw"]', 3),

('capitals', 'hard',   'What is the capital of Sri Lanka?',
 NULL, '["Galle","Kandy","Colombo","Sri Jayawardenepura Kotte"]', 3),

-- ── FLAGS (image-based) ───────────────────────────────────────────────────
('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/ca.png',
 '["Denmark","Norway","Canada","Switzerland"]', 2),

('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/jp.png',
 '["China","Japan","South Korea","Singapore"]', 1),

('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/br.png',
 '["Bolivia","Brazil","Colombia","Venezuela"]', 1),

('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/us.png',
 '["Australia","UK","New Zealand","USA"]', 3),

('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/de.png',
 '["Belgium","Austria","Germany","Hungary"]', 2),

('flags', 'easy', 'Whose flag is this?',
 'https://flagcdn.com/w160/fr.png',
 '["Netherlands","France","Luxembourg","Russia"]', 1),

('flags', 'medium', 'Whose flag is this?',
 'https://flagcdn.com/w160/za.png',
 '["Jamaica","Zimbabwe","South Africa","Ethiopia"]', 2),

('flags', 'medium', 'Whose flag is this?',
 'https://flagcdn.com/w160/ng.png',
 '["Ireland","Nigeria","Ivory Coast","Italy"]', 1),

('flags', 'medium', 'Whose flag is this?',
 'https://flagcdn.com/w160/pk.png',
 '["Algeria","Pakistan","Saudi Arabia","Malaysia"]', 1),

('flags', 'medium', 'Whose flag is this?',
 'https://flagcdn.com/w160/cy.png',
 '["Malta","Iceland","Cyprus","Ireland"]', 2),

('flags', 'medium', 'Whose flag is this?',
 'https://flagcdn.com/w160/mx.png',
 '["Peru","Mexico","Italy","Bolivia"]', 1),

('flags', 'hard', 'Whose flag is this?',
 'https://flagcdn.com/w160/bh.png',
 '["Qatar","Kuwait","Bahrain","Oman"]', 2),

('flags', 'hard', 'Whose flag is this?',
 'https://flagcdn.com/w160/np.png',
 '["Bhutan","Nepal","Tibet","Sri Lanka"]', 1),

('flags', 'hard', 'Whose flag is this?',
 'https://flagcdn.com/w160/kz.png',
 '["Uzbekistan","Kyrgyzstan","Kazakhstan","Turkmenistan"]', 2),

-- ── LANGUAGES ─────────────────────────────────────────────────────────────
('languages', 'easy',   'What is the official language of Brazil?',
 NULL, '["Spanish","French","English","Portuguese"]', 3),

('languages', 'easy',   'What language is primarily spoken in Egypt?',
 NULL, '["French","Arabic","Swahili","Turkish"]', 1),

('languages', 'easy',   'What is the official language of Mexico?',
 NULL, '["Nahuatl","Mayan","Portuguese","Spanish"]', 3),

('languages', 'easy',   'What is the most widely spoken language in the world by number of native speakers?',
 NULL, '["English","Spanish","Hindi","Mandarin Chinese"]', 3),

('languages', 'medium', 'What is the national language of Pakistan?',
 NULL, '["Hindi","Bengali","Urdu","Punjabi"]', 2),

('languages', 'medium', 'What is the national language of Kenya?',
 NULL, '["Swahili","Amharic","Hausa","Yoruba"]', 0),

('languages', 'medium', 'Which country has the most official languages?',
 NULL, '["India","Switzerland","Belgium","South Africa"]', 3),

('languages', 'medium', 'What language is spoken in Ethiopia as the official language?',
 NULL, '["Oromo","Swahili","Amharic","Somali"]', 2),

('languages', 'hard',   'What is the official language of Suriname?',
 NULL, '["English","French","Spanish","Dutch"]', 3),

('languages', 'hard',   'Which language has the most words in its dictionary?',
 NULL, '["French","German","English","Mandarin"]', 2),

-- ── CURRENCY ──────────────────────────────────────────────────────────────
('currency', 'easy',   'What currency does Japan use?',
 NULL, '["Won","Yen","Yuan","Baht"]', 1),

('currency', 'easy',   'What is the currency of India?',
 NULL, '["Taka","Rial","Rupee","Dinar"]', 2),

('currency', 'easy',   'What currency does the United States use?',
 NULL, '["Pound","Euro","Dollar","Franc"]', 2),

('currency', 'medium', 'What is the currency of Switzerland?',
 NULL, '["Euro","Krone","Lira","Swiss Franc"]', 3),

('currency', 'medium', 'What is the currency of Saudi Arabia?',
 NULL, '["Riyal","Dinar","Dirham","Pound"]', 0),

('currency', 'medium', 'Which of these countries uses the Euro?',
 NULL, '["Sweden","Norway","Denmark","Finland"]', 3),

('currency', 'medium', 'What is the currency of South Korea?',
 NULL, '["Yen","Yuan","Won","Ringgit"]', 2),

('currency', 'hard',   'What is the currency of Azerbaijan?',
 NULL, '["Tenge","Lari","Manat","Dram"]', 2),

('currency', 'hard',   'Which country uses the Zloty as its currency?',
 NULL, '["Czech Republic","Hungary","Romania","Poland"]', 3),

-- ── GEOGRAPHY (shapes & physical) ────────────────────────────────────────
('geography', 'easy',   'Which country is famously shaped like a boot?',
 NULL, '["Chile","Vietnam","Italy","Norway"]', 2),

('geography', 'easy',   'The mouth of the Amazon River is located in which country?',
 NULL, '["Peru","Colombia","Venezuela","Brazil"]', 3),

('geography', 'easy',   'Which is the largest country in the world by land area?',
 NULL, '["Canada","China","Russia","USA"]', 2),

('geography', 'easy',   'On which continent is Egypt located?',
 NULL, '["Asia","Europe","Africa","Middle East"]', 2),

('geography', 'medium', 'Which country has the most natural lakes in the world?',
 NULL, '["Russia","Canada","USA","Finland"]', 1),

('geography', 'medium', 'The Strait of Malacca separates which two landmasses?',
 NULL, '["India and Sri Lanka","Indonesia and Australia","Malay Peninsula and Sumatra","Africa and Madagascar"]', 2),

('geography', 'medium', 'Which country shares the longest land border with Russia?',
 NULL, '["China","Ukraine","Kazakhstan","Mongolia"]', 2),

('geography', 'medium', 'Mount Kilimanjaro is located in which country?',
 NULL, '["Kenya","Ethiopia","Uganda","Tanzania"]', 3),

('geography', 'hard',   'Which country has the highest number of neighbouring countries?',
 NULL, '["Russia","China","Brazil","Germany"]', 1),

('geography', 'hard',   'The Mariana Trench, the deepest point on Earth, is located in which ocean?',
 NULL, '["Atlantic","Indian","Arctic","Pacific"]', 3),

('geography', 'hard',   'Which of these rivers is the longest in the world?',
 NULL, '["Amazon","Congo","Yangtze","Nile"]', 3),

-- ── GOVERNMENT ────────────────────────────────────────────────────────────
('government', 'easy',   'Which of these countries is a republic?',
 NULL, '["United Kingdom","Japan","France","Saudi Arabia"]', 2),

('government', 'medium', 'Which of these countries is a constitutional monarchy?',
 NULL, '["USA","China","Sweden","Brazil"]', 2),

('government', 'medium', 'Which of these countries is governed as a federal republic?',
 NULL, '["Saudi Arabia","Germany","Cuba","Qatar"]', 1),

('government', 'medium', 'Which of these countries has a communist single-party government?',
 NULL, '["India","Cuba","China","Thailand"]', 2),

('government', 'medium', 'Which country uses a parliamentary system with a prime minister as head of government?',
 NULL, '["USA","Brazil","Canada","Mexico"]', 2),

('government', 'hard',   'Which country is widely regarded as the world''s oldest continuously governed republic?',
 NULL, '["Greece","USA","San Marino","Iceland"]', 2),

('government', 'hard',   'What type of government system does Switzerland use, where executive power is shared by a seven-member council?',
 NULL, '["Parliamentary republic","Presidential republic","Directorial republic","Federal monarchy"]', 2),

('government', 'hard',   'Which country has a theocratic government led by a Supreme Leader?',
 NULL, '["Saudi Arabia","Pakistan","Iran","Egypt"]', 2),

-- ── ALLIANCES ─────────────────────────────────────────────────────────────
('alliances', 'easy',   'What does "UN" stand for?',
 NULL, '["United Nations","Universal Network","Union of Nations","United Nexus"]', 0),

('alliances', 'medium', 'Which of these countries is NOT a member of NATO?',
 NULL, '["France","Canada","Switzerland","Germany"]', 2),

('alliances', 'medium', 'In which year was NATO founded?',
 NULL, '["1945","1951","1949","1955"]', 2),

('alliances', 'medium', 'What is the primary purpose of OPEC?',
 NULL, '["Regulate global trade","Coordinate petroleum policies","Promote democracy","Manage foreign aid"]', 1),

('alliances', 'medium', 'Which of these is NOT a permanent member of the UN Security Council?',
 NULL, '["USA","France","Germany","Russia"]', 2),

('alliances', 'hard',   'How many countries were founding members of the European Communities (predecessor to the EU)?',
 NULL, '["9","12","6","15"]', 2),

('alliances', 'hard',   'In which year was the African Union founded?',
 NULL, '["1963","1991","2010","2002"]', 3),

('alliances', 'hard',   'Which country was the first to leave the European Union?',
 NULL, '["Norway","Switzerland","United Kingdom","Iceland"]', 2),

('alliances', 'hard',   'The ASEAN bloc was founded in 1967 with how many original member states?',
 NULL, '["4","5","6","7"]', 1);

UPDATE questions
SET image_lookup_query = CASE
    WHEN category = 'capitals' AND question_text = 'What is the capital of France?' THEN 'Paris'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Australia?' THEN 'Canberra'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Brazil?' THEN 'Brasília'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Canada?' THEN 'Ottawa'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Japan?' THEN 'Tokyo'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Germany?' THEN 'Berlin'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Mexico?' THEN 'Mexico City'
    WHEN category = 'capitals' AND question_text = 'What is the (administrative) capital of South Africa?' THEN 'Pretoria'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Argentina?' THEN 'Buenos Aires'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Nigeria?' THEN 'Abuja'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Pakistan?' THEN 'Islamabad'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Indonesia?' THEN 'Jakarta'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Kazakhstan?' THEN 'Astana'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Myanmar?' THEN 'Naypyidaw'
    WHEN category = 'capitals' AND question_text = 'What is the capital of Sri Lanka?' THEN 'Sri Jayawardenepura Kotte'
    WHEN category = 'languages' AND question_text = 'What is the official language of Brazil?' THEN 'Rio de Janeiro'
    WHEN category = 'languages' AND question_text = 'What language is primarily spoken in Egypt?' THEN 'Cairo'
    WHEN category = 'languages' AND question_text = 'What is the official language of Mexico?' THEN 'Mexico City'
    WHEN category = 'languages' AND question_text = 'What is the most widely spoken language in the world by number of native speakers?' THEN 'Beijing'
    WHEN category = 'languages' AND question_text = 'What is the national language of Pakistan?' THEN 'Islamabad'
    WHEN category = 'languages' AND question_text = 'What is the national language of Kenya?' THEN 'Nairobi'
    WHEN category = 'languages' AND question_text = 'Which country has the most official languages?' THEN 'Cape Town'
    WHEN category = 'languages' AND question_text = 'What language is spoken in Ethiopia as the official language?' THEN 'Addis Ababa'
    WHEN category = 'languages' AND question_text = 'What is the official language of Suriname?' THEN 'Paramaribo'
    WHEN category = 'languages' AND question_text = 'Which language has the most words in its dictionary?' THEN 'London'
    WHEN category = 'currency' AND question_text = 'What currency does Japan use?' THEN 'Japanese yen'
    WHEN category = 'currency' AND question_text = 'What is the currency of India?' THEN 'Indian rupee'
    WHEN category = 'currency' AND question_text = 'What currency does the United States use?' THEN 'United States dollar'
    WHEN category = 'currency' AND question_text = 'What is the currency of Switzerland?' THEN 'Swiss franc'
    WHEN category = 'currency' AND question_text = 'What is the currency of Saudi Arabia?' THEN 'Saudi riyal'
    WHEN category = 'currency' AND question_text = 'Which of these countries uses the Euro?' THEN 'Euro'
    WHEN category = 'currency' AND question_text = 'What is the currency of South Korea?' THEN 'South Korean won'
    WHEN category = 'currency' AND question_text = 'What is the currency of Azerbaijan?' THEN 'Azerbaijani manat'
    WHEN category = 'currency' AND question_text = 'Which country uses the Zloty as its currency?' THEN 'Polish złoty'
    WHEN category = 'geography' AND question_text = 'Which country is famously shaped like a boot?' THEN 'Italy'
    WHEN category = 'geography' AND question_text = 'The mouth of the Amazon River is located in which country?' THEN 'Amazon River'
    WHEN category = 'geography' AND question_text = 'Which is the largest country in the world by land area?' THEN 'Russia'
    WHEN category = 'geography' AND question_text = 'On which continent is Egypt located?' THEN 'Egypt'
    WHEN category = 'geography' AND question_text = 'Which country has the most natural lakes in the world?' THEN 'Canada'
    WHEN category = 'geography' AND question_text = 'The Strait of Malacca separates which two landmasses?' THEN 'Strait of Malacca'
    WHEN category = 'geography' AND question_text = 'Which country shares the longest land border with Russia?' THEN 'Kazakhstan'
    WHEN category = 'geography' AND question_text = 'Mount Kilimanjaro is located in which country?' THEN 'Mount Kilimanjaro'
    WHEN category = 'geography' AND question_text = 'Which country has the highest number of neighbouring countries?' THEN 'China'
    WHEN category = 'geography' AND question_text = 'The Mariana Trench, the deepest point on Earth, is located in which ocean?' THEN 'Mariana Trench'
    WHEN category = 'geography' AND question_text = 'Which of these rivers is the longest in the world?' THEN 'Nile'
    WHEN category = 'government' AND question_text = 'Which of these countries is a republic?' THEN 'Paris'
    WHEN category = 'government' AND question_text = 'Which of these countries is a constitutional monarchy?' THEN 'Stockholm Palace'
    WHEN category = 'government' AND question_text = 'Which of these countries is governed as a federal republic?' THEN 'Reichstag building'
    WHEN category = 'government' AND question_text = 'Which of these countries has a communist single-party government?' THEN 'Great Hall of the People'
    WHEN category = 'government' AND question_text = 'Which country uses a parliamentary system with a prime minister as head of government?' THEN 'Parliament Hill'
    WHEN category = 'government' AND question_text = 'Which country is widely regarded as the world''s oldest continuously governed republic?' THEN 'Palazzo Pubblico (San Marino)'
    WHEN category = 'government' AND question_text = 'What type of government system does Switzerland use, where executive power is shared by a seven-member council?' THEN 'Bern'
    WHEN category = 'government' AND question_text = 'Which country has a theocratic government led by a Supreme Leader?' THEN 'Tehran'
    WHEN category = 'alliances' AND question_text = 'What does "UN" stand for?' THEN 'Headquarters of the United Nations'
    WHEN category = 'alliances' AND question_text = 'Which of these countries is NOT a member of NATO?' THEN 'Geneva'
    WHEN category = 'alliances' AND question_text = 'In which year was NATO founded?' THEN 'NATO Headquarters'
    WHEN category = 'alliances' AND question_text = 'What is the primary purpose of OPEC?' THEN 'OPEC'
    WHEN category = 'alliances' AND question_text = 'Which of these is NOT a permanent member of the UN Security Council?' THEN 'Berlin'
    WHEN category = 'alliances' AND question_text = 'How many countries were founding members of the European Communities (predecessor to the EU)?' THEN 'Treaties of Rome'
    WHEN category = 'alliances' AND question_text = 'In which year was the African Union founded?' THEN 'African Union Headquarters'
    WHEN category = 'alliances' AND question_text = 'Which country was the first to leave the European Union?' THEN 'Palace of Westminster'
    WHEN category = 'alliances' AND question_text = 'The ASEAN bloc was founded in 1967 with how many original member states?' THEN 'ASEAN Headquarters'
    ELSE image_lookup_query
END
WHERE category IN ('capitals', 'languages', 'currency', 'geography', 'government', 'alliances');
