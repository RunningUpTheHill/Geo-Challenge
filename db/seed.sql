USE geo_challenge;

INSERT INTO questions (category, difficulty, question_text, options, correct_index) VALUES

-- CAPITALS (6)
('capitals', 'easy',   'What is the capital of France?',
 '["Lyon","Paris","Bordeaux","Marseille"]', 1),

('capitals', 'easy',   'What is the capital of Australia?',
 '["Sydney","Melbourne","Canberra","Brisbane"]', 2),

('capitals', 'easy',   'What is the capital of Brazil?',
 '["São Paulo","Rio de Janeiro","Salvador","Brasília"]', 3),

('capitals', 'easy',   'What is the capital of Canada?',
 '["Ottawa","Toronto","Vancouver","Montreal"]', 0),

('capitals', 'easy',   'What is the capital of Japan?',
 '["Osaka","Kyoto","Hiroshima","Tokyo"]', 3),

('capitals', 'medium', 'What is the (administrative) capital of South Africa?',
 '["Cape Town","Johannesburg","Pretoria","Durban"]', 2),

-- FLAGS (4)
('flags', 'easy',   'Which country has a red maple leaf on its flag?',
 '["Japan","Denmark","Canada","Switzerland"]', 2),

('flags', 'medium', 'Which country''s flag features a crescent and star on a green background?',
 '["Algeria","Turkey","Saudi Arabia","Pakistan"]', 3),

('flags', 'medium', 'Which country''s flag has a map of the island on it?',
 '["Iceland","Malta","Ireland","Cyprus"]', 3),

('flags', 'medium', 'Which country''s flag has a red background with a yellow coat of arms in the center?',
 '["Spain","Germany","Mexico","Austria"]', 2),

-- LANGUAGES (5)
('languages', 'easy',   'What is the official language of Brazil?',
 '["Spanish","French","English","Portuguese"]', 3),

('languages', 'easy',   'What language is primarily spoken in Egypt?',
 '["French","Arabic","Swahili","Turkish"]', 1),

('languages', 'easy',   'What is the official language of Mexico?',
 '["Nahuatl","Mayan","Portuguese","Spanish"]', 3),

('languages', 'medium', 'What is the national language of Pakistan?',
 '["Hindi","Bengali","Urdu","Punjabi"]', 2),

('languages', 'medium', 'What is the national language of Kenya?',
 '["Swahili","Amharic","Hausa","Yoruba"]', 0),

-- CURRENCY (4)
('currency', 'easy',   'What currency does Japan use?',
 '["Won","Yen","Yuan","Baht"]', 1),

('currency', 'easy',   'What is the currency of India?',
 '["Taka","Rial","Rupee","Dinar"]', 2),

('currency', 'medium', 'What is the currency of Switzerland?',
 '["Euro","Krone","Lira","Swiss Franc"]', 3),

('currency', 'medium', 'What is the currency of Saudi Arabia?',
 '["Riyal","Dinar","Dirham","Pound"]', 0),

-- GEOGRAPHY (4)
('geography', 'easy',   'Which country is famously shaped like a boot?',
 '["Chile","Vietnam","Italy","Norway"]', 2),

('geography', 'easy',   'The mouth of the Amazon River is located in which country?',
 '["Peru","Colombia","Venezuela","Brazil"]', 3),

('geography', 'medium', 'Which country has the most natural lakes in the world?',
 '["Russia","Canada","USA","Finland"]', 1),

('geography', 'easy',   'Which is the largest country in the world by land area?',
 '["Canada","China","Russia","USA"]', 2),

-- GOVERNMENT (4)
('government', 'medium', 'Which of these countries is a constitutional monarchy?',
 '["USA","China","Sweden","Brazil"]', 2),

('government', 'medium', 'Which of these countries is governed as a federal republic?',
 '["Saudi Arabia","Germany","Cuba","Qatar"]', 1),

('government', 'hard',   'Which country is widely regarded as the world''s oldest continuously governed republic?',
 '["Greece","USA","San Marino","Iceland"]', 2),

('government', 'medium', 'Which of these countries has a communist single-party government?',
 '["India","Cuba","China","Thailand"]', 2),

-- ALLIANCES (4)
('alliances', 'medium', 'Which of these countries is NOT a member of NATO?',
 '["France","Canada","Switzerland","Germany"]', 2),

('alliances', 'hard',   'How many countries were founding members of the European Communities (predecessor to the EU)?',
 '["9","12","6","15"]', 2),

('alliances', 'hard',   'In which year was the African Union founded?',
 '["1963","1991","2010","2002"]', 3),

('alliances', 'medium', 'In which year was NATO founded?',
 '["1945","1951","1949","1955"]', 2);
