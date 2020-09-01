# Decta

Decta payment module for magento 2.X

## Manual install

1. Download the Payment Module archive, unpack it and upload its contents to a new folder <root>/app/code/ of your Magento 2 installation

2. Enable Payment Module
    ```text
    $ php bin/magento module:enable Decta_Decta --clear-static-content
    $ php bin/magento setup:upgrade
    ```
3. Deploy Magento Static Content (Execute If needed)
    ```text
    $ php bin / magento setup: static-content: deploy
    ```
## Installation via Composer

1. Go to Magento2 root folder

2. Enter following commands to install module:
   ```text
   composer config repositories.DECTA-Gate git https://github.com/DECTA-Gate/magento2.git
   composer require DECTA-Gate/magento2:dev-master 
   ``` 
   Wait while dependencies are updated.
3. Enter following commands to enable module:
   ```text
   php bin/magento module:enable Decta_Decta --clear-static-content
   php bin/magento setup:upgrade 
   ``` 
## Configuration

1. Login inside the Admin Panel and go to 
    ```text
    Stores -> Configuration -> Sales -> Payment Methods
    ```
2. If the Payment Module Panel Decta is not visible in the list of available Payment Methods, go to 
    ```text
    System -> Cache Management 
    ```
    and clear Magento Cache by clicking on Flush Magento Cache.

3. Go back to Payment Methods and set the settings.


## Unistall

```text
$ php bin/magento module:disable Decta_Decta
```

remove directory Decta from app/code manually

```text
$ php bin/magento setup:upgrade
$ php bin/magento cache:flush
```


