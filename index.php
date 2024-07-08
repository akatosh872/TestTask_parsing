<?php
require_once('vendor/autoload.php');

use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Facebook\WebDriver\Exception\WebDriverException;

$url = 'https://slotcatalog.com/en/Providers';

try {
    // For working need start chromedriver with port 4444 (./chromedriver --port=4444)
    $serverUrl = 'http://127.0.0.1:4444/';
    $driver = RemoteWebDriver::create($serverUrl, DesiredCapabilities::chrome());

    // Open provider page
    $driver->get($url);

    $driver->wait()->until(
        WebDriverExpectedCondition::presenceOfAllElementsLocatedBy(WebDriverBy::cssSelector('.providerCard'))
    );

    $text = $driver->findElement(WebDriverBy::cssSelector('.providerName'))->getText();

    echo $text;
} catch (WebDriverException $e) {
    echo "WebDriverException: " . $e->getMessage();
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage();
} finally {
    if (isset($driver)) {
        $driver->quit();
    }
}
