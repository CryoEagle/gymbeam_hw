<?php

use GuzzleHttp\Psr7\Response;

require_once __DIR__ . '/vendor/autoload.php';

class Product {
    public $name;
    public $notProcessedDescription;
    public $description;
    public $sentimentScore;

    public $neutralScore;
    public $posScore;
    public $negScore;

    function __construct($name, $notProcessedDescription, $description)
    {
        $this->name = $name;
        $this->notProcessedDescription = $notProcessedDescription;
        $this->description = $description;

        $this->sentimentScore = null;
    }

    public function makeScorePretty()
    {
        if ($this->sentimentScore == null) {
            return;
        }

        $this->neutralScore = $this->sentimentScore[0][0]->score;
        $this->posScore = $this->sentimentScore[0][1]->score;
        $this->negScore = $this->sentimentScore[0][2]->score;
    }
}

function getDir()
{
    return './data';
}

function saveProductsToFile($products) 
{
    file_put_contents(getDir(), serialize($products));
}

function getProductsFromFile()
{
    return unserialize(file_get_contents(getDir()));
}

// získáváme skóre sentimentu pomocí 3rd api
function determineSentimentScore($products)
{
    $client = new GuzzleHttp\Client();

    for ($i = 0; $i < count($products); $i++) 
    {
        $item = $products[$i];

        if ($item->sentimentScore != null) {
            continue;
        }

        try {
            $response = $client->request('POST', 'https://api-inference.huggingface.co/models/ProsusAI/finbert', [
                'headers' => [
                    'Authorization' => 'Bearer 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'
                ],
                'body' => json_encode([
                    'inputs' => $item->description
                ])
            ]);

            if ($response->getStatusCode() != 200) 
            {
                continue;
            }

            $responseData = json_decode($response->getBody());
            $products[$i]->sentimentScore = $responseData; // ukládáme data
            $products[$i]->makeScorePretty(); // hodíme data do čitelnější podoby
        } catch (Exception $e) {
            // too many requests a nebo jiný možný error
            break;
        }
    }

    saveProductsToFile($products);
    return $products;
}

// preprocessing dat z csv
function processData()
{
    $allProducts = array();
    $fileToRead = fopen('data.csv', 'r');

    if (file_exists(getDir())) 
    {
        return getProductsFromFile();
    }

    if ($fileToRead !== FALSE) 
    {
        $counter = 0;

        while (($data = fgetcsv($fileToRead, null, ',')) !== FALSE)
        {
            if ($counter != 0) 
            {
                $product = new Product($data[0], $data[1], strip_tags($data[1]));
                array_push($allProducts, $product);
            }

            $counter++;
        }
    }

    fclose($fileToRead);
    saveProductsToFile($allProducts);

    return $allProducts;
}

function filterNullScore($products) {
    return array_filter($products, function($obj) {
        if ($obj->sentimentScore == null) {
            return false;
        }

        return true;
    });
}

function findMaxScore($products, $property) 
{
    $products = filterNullScore($products);

    $lastHighIndex = -1;
    $lastHigh = 0;

    for ($i = 0; $i < count($products); $i++)
    {
        $product = $products[$i];

        if ($product->$property > $lastHigh) 
        {
            $lastHighIndex = $i;
            $lastHigh = $product->$property;
        }
    }

    return $lastHighIndex;
}

function findMostPositive($products) 
{
    return findMaxScore($products, "posScore");
}

function findMostNegative($products)
{
    return findMaxScore($products, "negScore");
}

function renderData($products) 
{
    foreach ($products as $product) 
    {
        $htmlToRender = <<< EOT

        <div>
            <h3>{title}</h3>
            <p>{description}</p>
            <div>
                neutral score: {neutral} positive score: {positive} negative score: {negative}
            </div>
        </div>

        EOT;

        $htmlToRender = str_replace("{title}", $product->name, $htmlToRender);
        $htmlToRender = str_replace("{description}", $product->description, $htmlToRender);
        $htmlToRender = str_replace("{neutral}", $product->neutralScore, $htmlToRender);
        $htmlToRender = str_replace("{positive}", $product->posScore, $htmlToRender);
        $htmlToRender = str_replace("{negative}", $product->negScore, $htmlToRender);

        echo($htmlToRender);
    }
}

$products = processData();
$finalProducts = determineSentimentScore($products);
$mostPositive = findMostPositive($finalProducts);
$mostNegative = findMostNegative($finalProducts);

echo("Nejvíce pozitivní: " . $finalProducts[$mostPositive]->name);
echo("<br/>");
echo("Nejvíce negativní: " . $finalProducts[$mostNegative]->name);

renderData($finalProducts);
print($mostNegative);

?>