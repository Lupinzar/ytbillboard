# ytbillboard

Yoot Tower Billboard Plugin Creator

This is a small bit of PHP code which will allow you to create Billboard Plugins for the game Yoot Tower. Code has inline documents that also help document the billboard plugin format to a degree. If you only want to create a billboard without running this code yourself, a live web interface exists for this code [here](https://garoux.net/page/yootbillboardcreator).

## Input Image Requirements

A billboard image should be a 256-color (8-bit per pixel) PNG with dimensions of 160x60 pixels. GD library will do its best to match colors, but it is recommeneded you do your own color matching against the palette.png file included in this repository.

## Example Usage

```php
require(dirname(__FILE__) . "/ytbillboard/autoloader.php");
$plugin = new \ytbillboard\plugin();
$plugin->setImagePath("test.png");  //path to your billboard image
$plugin->setOutputPath("test.t2p"); //path to your output
$plugin->setId("TES");
$plugin->setPluginName("Test Billboard");
$plugin->setCompanyName("Test Billboard");
$plugin->setContractLength(5); //game defaults
$plugin->setIncome(500); //game defaults
$plugin->outputFile();
```

## Additional Notes

Several exception classes are included to allow handling of specific errors from the library (i/o, invalid parameters, etc).

Created plugins have only been tested with the English Windows version of Yoot Tower. I do not have the ability to test Mac or Japanese language versions currently.
