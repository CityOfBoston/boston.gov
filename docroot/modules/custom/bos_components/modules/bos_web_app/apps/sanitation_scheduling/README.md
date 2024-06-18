# Sanitation Scehduling React App

## Build
From the sanitation_scheduling folder, ensure the command in `docker-compose.yml` is "build" and then run `docker-compose up sanitation`. This will create the necessary dist files in the `sanitation_scheduling/app/frontend/dist/assets` folder.

## Deploy
### To develop
Commit changes to the files in `sanitation_scheduling/app/frontend/src` into the repository `develop` branch. Github actions will run to build and deploy the app into Google Firebase. This Drupal module does not normally need to be updated or deployed.

### To Production
Merge the updated `develop` branch into `production`.  Github actions will run to build and deploy the app into Google Firebase. This Drupal module does not normally need to be updated.
