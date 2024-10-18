#!/bin/bash

# Define the YAML file path
config_file_path="metrolist.libraries.yml"

# Check if the YAML file exists and read the existing version
if [[ -f $config_file_path ]]; then
  existing_version=$(grep 'version:' "$config_file_path" | awk '{print $2}' | cut -d. -f1-2)  # Extract major.minor
  last_edited_timestamp=$(grep 'version:' "$config_file_path" | awk '{print $2}' | cut -d. -f3-6)  # Extract timestamp

  # Format the timestamp into a readable date
  last_edited_date=$(date -j -f "%Y%m%d%H%M" "$last_edited_timestamp" +"%b %d, %Y at %I:%M %p" 2>/dev/null)
  
  # Check if the date conversion was successful
  if [[ $? -ne 0 ]]; then
    last_edited_date="Invalid date format"
  fi
  
  echo "Current version: $existing_version"
  echo "Last edited on: $last_edited_date"
else
  read -p "No existing configuration file found. Create a new '$config_file_path'? (y/N) " create_new
  if [[ "$create_new" == "y" || "$create_new" == "yes" ]]; then
    existing_version="0.0"
  else
  	echo "No actions performed." 
    exit 0
  fi
fi

# Split the existing version into major and minor components
major_version=$(echo "$existing_version" | cut -d. -f1)
minor_version=$(echo "$existing_version" | cut -d. -f2)

# Ask if the user wants to upgrade the major version
new_major_version=$((major_version + 1))
read -p "Is this a major update? $existing_version -> $new_major_version.0 (y/N): " upgrade_major
upgrade_major=$(echo "$upgrade_major" | tr '[:upper:]' '[:lower:]')  # Convert to lowercase

if [[ "$upgrade_major" == "y" || "$upgrade_major" == "yes" ]]; then
  major_version=$new_major_version
  minor_version=0  # Reset minor version on major upgrade
else
  # Ask if the user wants to upgrade the minor version
  new_minor_version=$((minor_version + 1))
  read -p "Is this a minor update? $existing_version -> $major_version.$new_minor_version (Y/n): " upgrade_minor
  upgrade_minor=$(echo "$upgrade_minor" | tr '[:upper:]' '[:lower:]')  # Convert to lowercase

  if [[ "$upgrade_minor" != "n" && "$upgrade_minor" != "no" ]]; then
    minor_version=$new_minor_version
  fi
fi

# Get the current date and time for the timestamp
timestamp=$(date +'%Y%m%d%H%M')

# Create the full version string
full_version="${major_version}.${minor_version}.${timestamp}"

# Initialize an empty string for the JS file entries
js_entries=""

# Search for the specified files in ./dist
echo "Searching for files in ./dist..."
for file in ./dist/index.bundle.js ./dist/*.index.bundle.js; do
  if [[ -f $file ]]; then
    # Extract the file name without the path
    filename=$(basename "$file")
    dist_filename="dist/$filename"
    # Append the file entry to js_entries
    js_entries+="    $dist_filename: { preprocess: false, attributes: {type: text/javascript}}\n"
    echo "Found: $dist_filename"
  fi
done

# Prepare the content to be written to metrolist libraries.yaml
yaml_content="metrolist:
  version: ${full_version}
  js:
$js_entries  dependencies:
    - core/drupalSettings
"

# Write the YAML content to the file
echo "$yaml_content" > "$config_file_path"

echo "Configuration file 'metrolist.libraries.yml' has been update with newest build information."