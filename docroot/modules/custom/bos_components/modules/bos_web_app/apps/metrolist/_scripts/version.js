const XXHash = require( 'xxhash' );
const fs = require( 'fs' );
const path = require( 'path' );
// const { version } = require( 'os' );
// const yaml = require( 'js-yaml' );

const parameters = process.argv.slice( 2 );
const indexFilePath = path.resolve( __dirname, `../dist/index.bundle.js` );
const librariesFilePath = path.resolve( __dirname, `../../../bos_web_app.libraries.yml` );
const packageFilePath = path.resolve( __dirname, '../package.json' );
const indexFile = fs.readFileSync( indexFilePath );
const librariesFile = fs.readFileSync( librariesFilePath, 'utf8' );
const packageFile = fs.readFileSync( packageFilePath, 'utf8' );
const indexFileHash = XXHash.hash( indexFile, 0xC0FFEE );
let major = null;
let minor = null;
let newMajor = null;
let newMinor = null;
let newPatch = null;
let force = false;
const librariesVersionRegex = /(metrolist:[\s\n]+version:\s+)(.*)/;
const packageVersionRegex = /("version"\s*:\s*")(.*)(")/;
let currentVersion = librariesFile.match( librariesVersionRegex );

if ( currentVersion ) {
  [, , currentVersion] = currentVersion;
  const versionParts = currentVersion.split( '.' );

  if ( versionParts.length ) {
    [major, minor] = versionParts;
  }
}

parameters.forEach( ( parameter, index ) => {
  switch ( parameter ) {
    case '--help':
      console.log( `# version.js
Sets the version number for Metrolist in Drupal’s libraries.yml file.

 -m | --major\tSets the left version part, e.g. 2.x.x.
 \t\tIf omitted, major will be taken from existing Metrolist version.

 -n | --minor\tSets the middle version part, e.g. x.5.x.
 \t\tIf omitted, minor will be a hash of index.bundle.js for cache-busting.

 -p | --patch\tSets the right version part, e.g. x.x.3289.
 \t\tIf omitted while minor is set, patch will be a hash of index.bundle.js for cache-busting.
 \t\tIf omitted while minor is not set, patch will not be set.

 -f | --force\tAllow downgrading of Metrolist version.

 --help\t\tThis screen.
` );
      process.exit( 0 );
      break;

    case '-m':
    case '--major':
      newMajor = parameters[index + 1];
      break;

    case '-n':
    case '--minor':
      newMinor = parameters[index + 1];
      break;

    case '-p':
    case '--patch':
      newPatch = parameters[index + 1];
      break;

    case '-f':
    case '--force':
      force = true;
      break;

    default:
  }
} );

if ( !newMajor ) {
  newMajor = major;
}

if ( newMinor && !newPatch ) {
  newPatch = indexFileHash;
}

if ( !newMinor ) {
  if ( !newPatch ) {
    newMinor = indexFileHash;
  } else {
    newMinor = minor;
  }
}

if ( !force && ( newMajor < major ) ) {
  console.error( `The major version specified, “${newMajor}” is less than the current major version, “${major}”. If this is intentional use --force.` );
  process.exit( 1 );
}

const drupalDirectory = 'bos_web_app';
const newVersion = newPatch ? `${newMajor}.${newMinor}.${newPatch}` : `${newMajor}.${newMinor}`;
const versionBumpedLibrariesFile = librariesFile.replace( librariesVersionRegex, `$1${newVersion}` );
const versionBumpedPackageFile = packageFile.replace( packageVersionRegex, `$1${newVersion}$3` );

fs.writeFile( librariesFilePath, versionBumpedLibrariesFile, 'utf8', ( librariesWriteError ) => {
  if ( librariesWriteError ) {
    throw librariesWriteError;
  }

  console.log( `Updated version to “${newVersion}” in:\n ✔ ${path.basename( librariesFilePath )} under ${drupalDirectory}.` );

  fs.writeFile( packageFilePath, versionBumpedPackageFile, 'utf8', ( packageWriteError ) => {
    if ( packageWriteError ) {
      throw packageWriteError;
    }

    console.log( ` ✔ ${path.basename( packageFilePath )} under metrolist.` );
  } );
} );
