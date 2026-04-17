UPDATE questions
SET image_url = NULL
WHERE category IN ('capitals', 'languages', 'currency', 'geography', 'government', 'alliances')
  AND image_url LIKE 'public/img/questions/%';

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

UPDATE questions
SET question_text = 'Whose flag is this?',
    image_url = 'https://flagcdn.com/w160/ca.png'
WHERE category = 'flags'
  AND question_text = 'Which country has a red maple leaf on its flag?';

UPDATE questions
SET question_text = 'Whose flag is this?',
    image_url = 'https://flagcdn.com/w160/pk.png'
WHERE category = 'flags'
  AND question_text = 'Which country''s flag features a crescent and star on a green background?';

UPDATE questions
SET question_text = 'Whose flag is this?',
    image_url = 'https://flagcdn.com/w160/cy.png'
WHERE category = 'flags'
  AND question_text = 'Which country''s flag has a map of the island on it?';

UPDATE questions
SET question_text = 'Whose flag is this?',
    image_url = 'https://flagcdn.com/w160/mx.png'
WHERE category = 'flags'
  AND question_text = 'Which country''s flag has a red background with a yellow coat of arms in the center?';
