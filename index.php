<?php
require_once('vendor/autoload.php');

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\Exception\WebDriverException;
use Facebook\WebDriver\WebDriverExpectedCondition;
use League\Csv\ByteSequence;
use League\Csv\Writer;

// URL and file path
const BASE_URL = 'https://slotcatalog.com';
const CSV_FILE_PATH = __DIR__ . '/providers.csv';


try {
    // For working need start chromedriver with port 4444 (./chromedriver --port=4444)
    $serverUrl = 'http://127.0.0.1:4444/';
    $driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome());

    // Open providers page
    $driver->get(BASE_URL . '/en/Providers');

    // Wait for the page to load and check if the page is available
    try {
        $driver->wait()->until(
            WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(WebDriverBy::cssSelector('.providerCard'))
        );
    } catch (WebDriverException $e) {
        throw new Exception('Unable to load the providers page.');
    }

    // Array to store providers data
    $providersData = [];

    // Process pagination
    $page = 1;
    while (true) {
        // Collect provider data from the current page
        $providerElements = $driver->findElements(WebDriverBy::cssSelector('.providerCard'));
        array_pop($providerElements);   // remove "Show more element"

        foreach ($providerElements as $providerElement) {
            $name = $providerElement->findElement(WebDriverBy::cssSelector('.providerName'))->getText();
            $link = $providerElement->findElement(WebDriverBy::cssSelector('.providerName'))->getAttribute('href');
            $image = $providerElement->findElement(WebDriverBy::cssSelector('.providerImage img'))->getAttribute('src');

            // Save data for next usage
            $providersData[] = [
                'name' => $name,
                'link' => BASE_URL . $link,
                'image' => $image,
            ];
        }

        // Navigate to the next page if available
        $nextPageLink = findElementOrFalse($driver, WebDriverBy::cssSelector('.navpag a.aAjaxSubmit[blkno="1"][p="' . ++$page . '"]'));
        if ($nextPageLink) {
            $driver->executeScript("arguments[0].scrollIntoView(true);", [$nextPageLink]);
            $driver->executeScript("arguments[0].click();", [$nextPageLink]);

            // Wait while loading
            sleep(1);
        } else {
            break; // No more pages found
        }
    }

    // Quit the driver session
    $driver->quit();

    // Process data for each provider using cURL and DOM
    echo "Parsed data from each provider. This may take a long time, do not turn off the script";

    foreach ($providersData as &$provider) {
        $html = fetchHtml($provider['link']);

        if (empty($html)) {
            throw new Exception('Failed to fetch HTML content for ' . $provider['link']);
        }

        // Create DOMDocument and DOMXPath objects
        $dom = new DOMDocument;
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $provider['founded'] = getElementText($xpath, '//td[@data-label="Founded"]', 'Unknown founded year');
        $provider['website'] = getElementAttribute($xpath, '//td[@data-label="Website"]/a', 'href', 'No website');
        $provider['totalGames'] = getElementText($xpath, '//td[@data-label="Total Games"]', '0');
        $provider['videoSlots'] = getElementText($xpath, '//td[@data-label="Video Slots"]', '0');
        $provider['classicSlots'] = getElementText($xpath, '//td[@data-label="Classic Slots"]', '0');
        $provider['cardGames'] = getElementText($xpath, '//td[@data-label="Card games"]', '0');
        $provider['rouletteGames'] = getElementText($xpath, '//td[@data-label="Roulette Games"]', '0');
        $provider['liveCasinoGames'] = getElementText($xpath, '//td[@data-label="Live Casino Games"]', '0');
        $provider['scratchTickets'] = getElementText($xpath, '//td[@data-label="Scratch tickets"]', '0');
        $provider['otherTypes'] = getElementText($xpath, '//td[@data-label="Other types"]', '0');
        $provider['casinos'] = getElementText($xpath, '//div[@class="provider_prop_item"]//p[@class="prop_number"]', '0');

        // Parse countries
        $countries = $xpath->query('//div[@class="sixTableStat"]//tbody//tr//td[@data-label="Country"]/a[@class="linkOneLine"]');
        if ($countries->length > 0) {
            $provider['countries'] = implode(';', array_map(function ($country) {
                return $country->textContent;
            }, iterator_to_array($countries)));
        } else {
            $provider['countries'] = 'No country data';
        }
    }

    // Write data to CSV
    writeCsv($providersData, CSV_FILE_PATH);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}

/**
 * Function to fetch HTML using curl
 *
 * @param $url
 * @return string
 */
function fetchHtml($url): string
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $html = curl_exec($ch);
    if (curl_errno($ch)) {
        curl_close($ch);
        return '';
    }
    curl_close($ch);
    return $html;
}

/**
 * Function parse and get element text using XPath
 *
 * @param $xpath
 * @param $query
 * @param string $default
 * @return string
 */
function getElementText($xpath, $query, string $default = ''): string
{
    $elements = $xpath->query($query);
    if ($elements->length > 0) {
        return $elements->item(0)->textContent;
    } else {
        return $default;
    }
}

/**
 * Parse and get element attribute using XPath
 *
 * @param $xpath
 * @param $query
 * @param $attribute
 * @param string $default
 * @return string
 */
function getElementAttribute($xpath, $query, $attribute, string $default = ''): string
{
    $elements = $xpath->query($query);
    if ($elements->length > 0) {
        return $elements->item(0)->getAttribute($attribute) ?? $default;
    } else {
        return $default;
    }
}

/**
 * Find an element and return it, or return false if not found.
 *
 * @param RemoteWebDriver $driver
 * @param WebDriverBy $by
 * @return \Facebook\WebDriver\WebDriverElement|false
 */
function findElementOrFalse(RemoteWebDriver $driver, WebDriverBy $by)
{
    try {
        return $driver->findElement($by);
    } catch (WebDriverException $e) {
        return false;
    }
}

/**
 * Write data to CSV file.
 *
 * @param array $data
 * @param string $filePath
 * @throws \League\Csv\CannotInsertRecord
 * @throws \League\Csv\Exception
 */
function writeCsv(array $data, string $filePath): void
{
    if (empty($data)) {
        throw new Exception('No data available to write to CSV.');
    }

    $csv = Writer::createFromPath($filePath, 'w+');
    $csv->setOutputBOM(ByteSequence::BOM_UTF8); // Ensure UTF-8 BOM
    $csv->setDelimiter(';'); // set delimiter
    $header = [
        'Name', 'Link', 'Image', 'Founded', 'Website', 'Total Games', 'Video Slots', 'Classic Slots',
        'Card Games', 'Roulette Games', 'Live Casino Games', 'Scratch Tickets', 'Other Types', 'Casinos', 'Countries'
    ];
    $csv->insertOne($header);

    foreach ($data as $row) {
        $csv->insertOne([
            $row['name'],
            $row['link'],
            $row['image'],
            $row['founded'],
            $row['website'],
            $row['totalGames'],
            $row['videoSlots'],
            $row['classicSlots'],
            $row['cardGames'],
            $row['rouletteGames'],
            $row['liveCasinoGames'],
            $row['scratchTickets'],
            $row['otherTypes'],
            $row['casinos'],
            $row['countries']
        ]);
    }
}
