<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Jeu de mot mélangé</title>
    <style>
        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Regular.ttf") format("truetype");
            font-weight: 400;
            font-style: normal;
        }

        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Bold.ttf") format("truetype");
            font-weight: 700;
            font-style: normal;
        }

        @font-face {
            font-family: "Inter";
            src: url("Inter/static/Inter-Black.ttf") format("truetype");
            font-weight: 900;
            font-style: normal;
        }

        body {
            font-family: "Inter", sans-serif;
            background: #eee;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }

        .container {
            background: #fff;
            padding: 95px;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 1000px;
            width: 100%;
            opacity: 0;
            transform: translateY(-20px);
            animation: slideIn 0.5s forwards;
        }

        .title {
            font-size: 64px;
            margin-top: 45px;
        }

        p {
            font-size: 32px;
        }

        .scrambled-word {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 70px;
        }

        .letter-box {
            width: 140px;
            height: 140px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 10px;
            font-size: 120px;
            font-weight: bold;
            border-radius: 35px;
            background-color: #f2f2f2;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        #original-word {
            display: none;
            font-size: 54px;
        }

        #reveal-text {
            margin-bottom: 60px;
        }

        @keyframes slideIn {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>

<body onload="revealWord()">
    <div class="container">
        <?php
        error_reporting(0);
        ini_set('display_errors', 0);
        date_default_timezone_set('Europe/Zurich');

        require 'vendor/autoload.php';

        use GuzzleHttp\Client;

        // Définition du chemin du fichier JSON
        $jsonFilePath = 'words.json';
        $txtFilePath = 'used_words.txt';

        // Obtenir la date actuelle et le moment de la journée (AM/PM)
        $currentDate = date('Y-m-d');
        $currentTime = date('A');

        $randomWord = '';
        $scrambledWord = '';
        $anecdote = '';

        // Vérifiez si le fichier JSON existe
        if (file_exists($jsonFilePath)) {
            $jsonContent = file_get_contents($jsonFilePath);
            $wordsData = json_decode($jsonContent, true);

            // Vérifiez si un mot et une anecdote sont disponibles pour aujourd'hui et ce moment de la journée
            if (isset($wordsData[$currentDate][$currentTime])) {
                $randomWord = $wordsData[$currentDate][$currentTime]['word'];
                $scrambledWord = $wordsData[$currentDate][$currentTime]['scrambled'];
                $anecdote = $wordsData[$currentDate][$currentTime]['anecdote'];
            }
        } else {
            $wordsData = [];
        }

        // Si aucun mot n'est trouvé pour le moment de la journée, récupérez un nouveau mot et une nouvelle anecdote
        if (empty($randomWord)) {
            $usedWords = file_exists($txtFilePath) ? file($txtFilePath, FILE_IGNORE_NEW_LINES) : [];

            do {
                $client = new Client(['verify' => false]);
                $response = $client->post(
                    'https://api.openai.com/v1/chat/completions',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer OPENAI-API-KEY',
                        ],
                        'json' => [
                            'model' => 'gpt-4o',
                            'messages' => [
                                ['role' => 'system', 'content' => 'Ton devoir est de me donner un mot courant aléatoire relativement commun du dictionnaire français (mais pas trop facile non plus, genre pas "pomme"). Pas de nom propre. Réponds uniquement avec le mot en question, pas une phrase. Tout en minuscules. Minimum 7 lettres, Maximum 9 lettres.'],
                                ['role' => 'user', 'content' => ''],
                            ],
                        ],
                    ]
                );

                $responseBody = json_decode($response->getBody()->getContents(), true);
                $randomWord = $responseBody['choices'][0]['message']['content'];

                // Supprime les caractères spéciaux et les sauts de ligne
                $randomWord = preg_replace("/[^a-zA-ZÀ-ÿ]/", "", $randomWord);

                // Génération de l'anecdote
                $response = $client->post(
                    'https://api.openai.com/v1/chat/completions',
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => 'Bearer OPENAI-API-KEY',
                        ],
                        'json' => [
                            'model' => 'gpt-4o',
                            'messages' => [
                                ['role' => 'system', 'content' => "Ton devoir est de m'écrire une phrase intéressante de culture G à propos du mot \"$randomWord\". La phrase doit être adaptée à tout public. Je veux uniquement la phrase en question."],
                                ['role' => 'user', 'content' => ''],
                            ],
                        ],
                    ]
                );

                $responseBody = json_decode($response->getBody()->getContents(), true);
                $anecdote = $responseBody['choices'][0]['message']['content'];

                // Vérifie si le mot a déjà été utilisé
                $alreadyUsed = false;
                foreach ($wordsData as $date => $timeData) {
                    foreach ($timeData as $time => $wordData) {
                        if ($wordData['word'] === $randomWord) {
                            $alreadyUsed = true;
                            break 2;
                        }
                    }
                }
                // Ajoutez cette vérification supplémentaire pour les mots dans used_words.txt
                if (in_array($randomWord, $usedWords)) {
                    $alreadyUsed = true;
                }
            } while ($alreadyUsed);

            $scrambledWord = str_shuffle($randomWord);

            $wordsData[$currentDate][$currentTime] = ['word' => $randomWord, 'scrambled' => $scrambledWord, 'anecdote' => $anecdote];
            file_put_contents($jsonFilePath, json_encode($wordsData));

            // Ajouter le mot à la liste des mots déjà utilisés et enregistrer dans used_words.txt
            $usedWords[] = $randomWord;
            file_put_contents($txtFilePath, implode("\n", $usedWords));
        }
        ?>
        <p class="title">Retrouvez le mot mélangé :</p>
        <div id="word-container" class="scrambled-word">
            <?php
            for ($i = 0; $i < strlen($scrambledWord); $i++) {
                echo "<div class='letter-box'>{$scrambledWord[$i]}</div>";
            }
            ?>
        </div>
        <div id="original-word" style="display: none;">
            <?php
            for ($i = 0; $i < strlen($randomWord); $i++) {
                echo "<div class='letter-box'>{$randomWord[$i]}</div>";
            }
            ?>
        </div>
        <p id="reveal-text" style="display: none;"><?php echo $anecdote; ?></p>
    </div>
    <script>
        function revealWord() {
            setTimeout(() => {
                const originalWordElement = document.getElementById('original-word');
                const revealText = document.getElementById('reveal-text');
                const wordContainer = document.getElementById('word-container');

                originalWordElement.style.display = 'flex';
                revealText.style.display = 'block';
                wordContainer.style.display = 'none';

                document.querySelector('.container').style.paddingTop = '45px';
                document.querySelector('.container').style.paddingBottom = '45px';
            }, 35000); // Ici, le mot original sera révélé après 35 secondes
        }
    </script>
</body>

</html>
