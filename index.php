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

    $driver->wait()->until(
        WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(WebDriverBy::cssSelector('.providerCard'))
    );

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

    // Process data for each provider
    foreach ($providersData as &$provider) {
        $driver->get($provider['link']);

        $provider['founded'] = getElementText($driver, '//td[@data-label="Founded"]', 'Unknown founded year');
        $provider['website'] = getElementAttribute($driver, '//td[@data-label="Website"]/a', 'href', 'No website');
        $provider['totalGames'] = getElementText($driver, '//td[@data-label="Total Games"]', '0');
        $provider['videoSlots'] = getElementText($driver, '//td[@data-label="Video Slots"]', '0');
        $provider['classicSlots'] = getElementText($driver, '//td[@data-label="Classic Slots"]', '0');
        $provider['cardGames'] = getElementText($driver, '//td[@data-label="Card games"]', '0');
        $provider['rouletteGames'] = getElementText($driver, '//td[@data-label="Roulette Games"]', '0');
        $provider['liveCasinoGames'] = getElementText($driver, '//td[@data-label="Live Casino Games"]', '0');
        $provider['scratchTickets'] = getElementText($driver, '//td[@data-label="Scratch tickets"]', '0');
        $provider['otherTypes'] = getElementText($driver, '//td[@data-label="Other types"]', '0');
        $provider['casinos'] = getElementText($driver, '//div[@class="provider_prop_item"]//p[@class="prop_number"]', '0');


        // Parse countries
        $countries = $driver->findElements(WebDriverBy::xpath('//div[@class="sixTableStat"]//tbody//tr//td[@data-label="Country"]/a[@class="linkOneLine"]'));
        if (count($countries) > 0) {
            $provider['countries'] = implode(';', array_map(function ($country) {
                return $country->getText();
            }, $countries));
        } else {
            $provider['countries'] = 'No country data';
        }
        file_put_contents(__DIR__ . '/text.txt', '<pre>' . print_r([$provider], 1), 8);
    }

    // Write data to CSV
    writeCsv($providersData, CSV_FILE_PATH);

} catch (WebDriverException | Exception $e) {
    echo "Error: " . $e->getMessage();
} finally {
    // Quit the driver session
    if (isset($driver)) {
        $driver->quit();
    }
}

/**
 * Get the text of an element located by xpath.
 *
 * @param RemoteWebDriver $driver
 * @param string $xpath
 * @param string $default
 * @return string
 */
function getElementText(RemoteWebDriver $driver, string $xpath, string $default = ''): string
{
    $elements = $driver->findElements(WebDriverBy::xpath($xpath));
    if (count($elements) > 0) {
        return $elements[0]->getText() ?? $default;
    } else {
        return $default;
    }
}

/**
 * Get the attribute of an element located by xpath.
 *
 * @param RemoteWebDriver $driver
 * @param string $xpath
 * @param string $attribute
 * @param string $default
 * @return string
 */
function getElementAttribute(RemoteWebDriver $driver, string $xpath, string $attribute, string $default): string
{
    $elements = $driver->findElements(WebDriverBy::xpath($xpath));
    if (count($elements) > 0) {
        return $elements[0]->getAttribute($attribute);
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
function writeCsv(array $data, string $filePath)
{
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
