# Boston.gov My Neighborhood

## Overview
The My Neighborhood app is built using REACT to poplulate the front-end and Gulp as the deploy and build tool to bundle / transpile the code. The back-end data is stored in Drupal and accessed via AJAX/Fetch and Drupal's [JSON:API](https://www.drupal.org/docs/core-modules-and-themes/core-modules/jsonapi-module/jsonapi) module and service. Furthermnore, Drupal receives its data from Civis on a scheduled routine which is processed server side by Drupal/Acquia).

## Getting Started
The Drupal Web Apps are currently dependent on Node.js and either Gulp or Webpack to bundle and transpile the code base. Instructions are below.

#### Node and NPM (node package manager)

[https://nodejs.org] (https://nodejs.org/en)

Cofirm that you have the latest version.

```shell
npm install npm@latest -g
```

#### Gulp and Gulp CLI (command line interface)

```shell
npm install gulp-cli -g
npm install gulp
```


#### Webpack

```shell
npm install --save-dev webpack
```

## Editing / updating source code
The Web Apps file structure has 2 main directories, `src` and `dist`. The source files you edit will be in `src` and the files to be bundled and referenced from the [Web Apps Library](../../bos_web_app.libraries.yml) will be in the `dist` folder. 

__*The `dist` folder's contents should never be edited directly.*__

1. Browse to the project docroot using the command line

```shell
cd <your_local_directory_path>/boston.gov-d8/docroot/modules/custom/bos_components/modules/bos_web_app/<web_app_name>
``` 

2. Install node packes using npm

  
```shell
npm install
``` 

__*Make sure a `package.json` file exists or else npm can't install needed packages / dependencies.*__

3. Start Gulp
The default `Gulp` command will start Gulp and "watch" any changed files.

```shell
gulp
``` 

4. Edit relevant files found in `src` directory



