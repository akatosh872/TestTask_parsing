<?php
require_once('vendor/autoload.php');

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\WebDriverException;


$url = 'https://slotcatalog.com';

try {
    // For working need start chromedriver with port 4444 (./chromedriver --port=4444)
    $serverUrl = 'http://127.0.0.1:4444/';
    $driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome());

    // Open providers page
    $driver->get($url . '/en/Providers');

    $driver->wait()->until(
        WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(WebDriverBy::cssSelector('.providerCard'))
    );

    $providerElements = $driver->findElements(WebDriverBy::cssSelector('.providerCard'));
    array_pop($providerElements);   // remove "Show more element"

    $providersData = [];

    foreach ($providerElements as $providerElement) {
        $name = $providerElement->findElement(WebDriverBy::cssSelector('.providerName'))->getText();
        $link = $providerElement->findElement(WebDriverBy::cssSelector('.providerName'))->getAttribute('href');
        $image = $providerElement->findElement(WebDriverBy::cssSelector('.providerImage img'))->getAttribute('src');

        // Save data for next usage
        $providersData[] = [
            'name' => $name,
            'link' => $link,
            'image' => $image,
        ];
    }

    foreach ($providersData as $provider) {
        $driver->get($url . $provider['link']);

        $founded = getElementText($driver, '//td[@data-label="Founded"]', 'Unknown founded year');
        $website = getElementAttribute($driver, '//td[@data-label="Website"]/a', 'href', 'No website');
        $totalGames = getElementText($driver, '//td[@data-label="Total Games"]', "0");
        $videoSlots = getElementText($driver, '//td[@data-label="Video Slots"]', "0");
        $classicSlots = getElementText($driver, '//td[@data-label="Classic Slots"]', "0");
        $cardGames = getElementText($driver, '//td[@data-label="Card games"]', "0");
        $rouletteGames = getElementText($driver, '//td[@data-label="Roulette Games"]', "0");
        $liveCasinoGames = getElementText($driver, '//td[@data-label="Live Casino Games"]', "0");
        $scratchTickets = getElementText($driver, '//td[@data-label="Scratch tickets"]', "0");
        $otherTypes = getElementText($driver, '//td[@data-label="Other types"]', "0");

        file_put_contents(__DIR__ . '/text.txt', '<pre>' . print_r([$website], 1), 8);
    }
    // Return providers page
    $driver->get($url . '/en/Providers');

} catch (WebDriverException $e) {
    echo "WebDriverException: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage();
} finally {
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
function getElementText(RemoteWebDriver $driver, string $xpath, string $default) : string
{
    $elements = $driver->findElements(WebDriverBy::xpath($xpath));
    if (count($elements) > 0) {
        return $elements[0]->getText();
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
function getElementAttribute(RemoteWebDriver $driver, string $xpath, string $attribute, string $default) : string
{
    $elements = $driver->findElements(WebDriverBy::xpath($xpath));
    if (count($elements) > 0) {
        return $elements[0]->getAttribute($attribute);
    } else {
        return $default;
    }
}