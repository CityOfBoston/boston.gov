##Image folder structure
```
sites/default/files
    ├── file                           DOCUMENT FILES ROOT
    |   ├── [dated]
    |   └── document_files
    |       ├── [year]
    |       └── unk
    ├── embed                          RICH-TEXT EDITOR ROOT
    |   ├── [file-firstchar]
    |   └── file
    |       └── [dated]
    ├── img                            IMAGE ROOT FOLDER
    |   ├── [dated]                     - migrated files from public:// (root)
    |   ├── columns                    * Paragraph columns 
    |   |   └── [dated]                 - migrated files
    |   ├── event                      * Node event
    |   |   ├── intro_images            - from field_intro_image
    |   |   └── thumbnails              - from field_thumbnail
    |   ├── hero_image                 * Paragraph hero_image 
    |   |   └── [dated]                 - migrated files
    |   ├── how_to                     * Node how_to 
    |   |   └── intro_images            - from field_intro_image
    |   ├── icons                      * Holds svg icon files 
    |   |   ├── department              
    |   |   |   └── [dated]             - migrated files
    |   |   ├── emergency                  
    |   |   |   └── [dated]             - migrated files
    |   |   ├── feature                  
    |   |   |   └── [dated]             - migrated files
    |   |   ├── fyi                      
    |   |   |   └── [dated]             - migrated files
    |   |   ├── site_alert                   
    |   |   |   └── [dated]             - migrated files
    |   |   ├── status                   
    |   |   |   └── [dated]             - migrated files
    |   |   └── transactions             
    |   |       └── [dated]             - migrated files
    |   ├── library                    * Holds photographic images 
    |   |   └── photos                   
    |   |       └── [dated]             - migrated files
    |   ├── listing_page               * Node listing_page 
    |   |   └── intro_images            - from field_intro_image
    |   ├── maps                       * Paragraph listing_page 
    |   |   └── [dated]                 - from field_intro_image
    |   ├── person_profile             * Node person_profile
    |   |   └── photos                  - from field_photos
    |   ├── place_profile              * Paragraph place_profile
    |   |   └── intro_images            - from field_intro_image
    |   ├── post                       * Node post
    |   |   ├── intro_images            - from field_intro_image
    |   |   └── thumbnails              - from field_thumbnail
    |   ├── program                    * Node program_initiative_profiles
    |   |   ├── intro_images            - from field_intro_image
    |   |   └── logo                    - from field_logo
    |   ├── quote_person               * Paragraph quote
    |   |   └── photos                  - from field_photos
    |   ├── tabbed                     * Node tabbed
    |   |   └── intro_images            - from field_intro_image
    |   ├── topic                      * Node topic_page (guide)
    |   |   ├── intro_images            - from field_intro_image
    |   |   └── thumbnails              - from field_thumbnail
    |   ├── unk                        * Unknown origin (to migration)
    |   |   ├── intro_images            
    |   |   └── thumbnails              
    |   ├── user                       * User object field.
    |   |   └── photos                  - from user_picture
    |   └── video                      * Paragraph video
    |       └── [dated]                 - migrated files
    ├── private                         PRIVATE FILES
    └── unk                             UNKNOWN ORIGIN (to migration) FILES
    
```     
##Image styles used
   
|Entity | Field | min/max resolution & max filesize  | View: Style |   
|:-----|:-----|-----:|:-----|
| **Images** |
| node:department_profile | field_icon | 56x56/++ - 200KB | default: (i) square_icon_56px<br>Article: (i) square_icon_56px<br>Card: (i) square_icon_56px<br>Article: not displayed<br>Published By: (i) square_icon_56px |
| node:event | field_intro_image | 1440x396/++ 8 MB | default: (b) intro_image_fields<br>featured_item: (i) Featured Item Thumbnail |
| | field_thumbnail | 525x230/++ 8 MB | default: (b) thumbnail_event<br>featured_item: (p) thumbnail_event |
| node:how_to | field_intro_image | 1440x396/++ 8 MB  | default: (b) intro_image_fields<br>[all others (10)] not displayed |
| node:listing_page | field_intro_image | 1440x396/++ 8MB  | default: (b) intro_image_fields<br>[all others (12)]: not displayed |
| node:person_profile | field_person_photo | 350x350/++ 5MB | default: (p) person_photos<br>listing: (p) person_photos<br>embed: (p) person_photos |
| node:place_profile | field_intro_image | 1440x396/++ 8MB  | default: (b) intro_image_fields<br>Listing: (p) card_images<br>Teaser: not displayed |
| node:post | field_intro_image | 1440x396/++ 8MB | default: (b) intro_image_fields<br>featured_item: not displayedListing: not displayed<br>Listing short: not displayed<br>Teaser: not displayed |
| | field_thumbnail | 700x700/++ 5MB  | default: not displayed<br>featured_item: (p) featured_images<br>Listing: (i) News Item -thumbnail (725x725)<br>Listing short: (i) News Item -thumbnail (725x725)<br>Teaser: (i) News Item -thumbnail (725x725) |
| node:program_i_p | field_intro_image | 1440x396/++ 8MB  | default: (b) intro_image_fields <br>listing: (b) card_images |
| | field_program_logo | 800x800/++ 2MB | default: (p) logo_images<br>Listing: not displayed |
| node:site_alert | field_icon | 56x56/++ - 200KB | default: (s) n/a svg (square_icon_56px)<br>Embed: (i) square_icon_56px<br>Teaser: not displayed |
| node:status_item | field_icon | 65x65/++ - 200KB | default: (s) n/a svg (square_icon_65px)<br>listing: (s) n/a svg (square_icon_65px)<br>teaser: (s) n/a svg (square_icon_65px) |
| node:tabbed_content | field_intro_image | 1440x396/++ 8MB  | default: (b) intro_image_fields  |
| node:topic_page | field_intro_image | 1440x396/++ 8MB  | default: (b) intro_image_fields<br>featured_topic not displayed<br>listing_long: (b) intro_image_fields<br>listing: (b) card_images |
| | field_thumbnail |   | default: not displayed<br>featured_topic (p) featured_images: not displayed<br>listing: not displayed<br>listing_long: not displayed |
| para:card | field_thumbnail | 670x235/++ 2MB | default: (b) card_images |
| para:columns | field_image | 200x200/++ 2MB | default: (i) Med Small Square (also Person photo a-mobile 1x (110x110))  |
| para:fyi | field_icon | 56x56/++ 200KB | default: (s) n/a svg (square_icon_56px) |
| para:hero_image | field_image | 1440x800/++ 8 MB | default: (b) Hero fixed image fields<br>Separated Title: not displayed|
| para:map | field_image | 1440x800/++ 8 MB | default: (b) Photo Bleed Images |
| para:photo | field_image | 1440x800/++ 8 MB | default: (b) Photo Bleed Images |
| para:quote | field_person_photo | 350x350/++ 5 MB | default: (i) Person photo a-mobile 1x (110x110) |
| para:signup_emergency_alerts | field_icon | n/a svg  | default: (s) n/a svg (square_icon_65px) |
| para:transactions | field_icon | 180x100/++ - 2MB  | default: (i) transaction_icon_180x100<br>group_of_links: (i) transaction_icon_180x100 |
| para:video | field_image | 1440x800/++ 8 MB | default: (b) Photo Bleed Images |
| tax:features | field_icon | svg  | default: (s) n/a svg (square_icon_56px)<br>sidebar_right: (s) n/a svg (square_icon_56px) |
| entity:user | user_picture | 100x100/1024/1024 1 MB | default: (p) person_photos<br>compact: (i) Person photo a-mobile 1x (110x110) |
| entity:media.image | image | +++/2400/2400 8 MB  | default: (i) original image <br>[all others]: (i) Media Fixed Height (100px) |
| **Files** |
| media.document | field_document |   |  |
| node:procurement | field_document |   |  |
| para:document | field_document |   |  |


**Key**  
 ++ = not specified (unlimited)  
(b) = background, responsive.  
(p) = HTML5 Picture, responsive.  
(i) = Image, svg or picture, non-reponsive.  
(s) = svg.  
---

##Site Breakpoints
The following breakpoint grups and breakpoints are defined in D8:   

| Breakpoint | Start width | end width | note |   
|:-----|-----|-----|-----|
| **group: hero** |
| mobile | 0 | 419 |  | 
| tablet | 420 | 767 |  | 
| desktop | 768 | 1439 |  | 
| large | 1440 | 1919 | Introduced in D8 | 
| oversize | 1920 | +++ | have a notional max-width of 2400px | 
| **group: card** |
| mobile | 0 | 419 |  | 
| tablet | 420 | 767 |  | 
| desktop | 768 | 839 |  | 
| desktop | 840 | 1439 |  | 
| large | 1440 | 1919 |  | 
| oversize | 1920 | +++ | have a notional max-width of 2400px | 
| **group: person** |
| mobile | 0 | 839 |  | 
| tablet | 840 | 979 |  | 
| desktop | 980 | 1279 | There is also a breakpoint at 1300 in node:pip | 
| desktop | 1280 | +++ | have a notional max-width of 2400px | 

## Responsive Styles
| Breakpoint | responsive Style | style | size |   
|:-----|-----|-----|-----|
| **All Nodes: field_intro_image**<br>(excluding node:post) | 
| hero: mobile (<419px)| intro_image_fields | Intro image a-mobile 1x | 420x115 |
| hero: tablet (420-767px)| intro_image_fields | Intro image b-tablet 1x | 768x215 |
| hero: desktop (768-1439x)| intro_image_fields | Intro image c-desktop 1x | 1440x396 |
| hero: large (1440-1919px)| intro_image_fields | Intro image d-large 1x | 1920x528 |
| hero: oversize (>1920px)| intro_image_fields | Intro image e-oversize 1x | 2400x660 |
| **node:post field_intro_image** | 
| hero: mobile (<419px)| Hero fixed image fields | Hero fixed a-mobile 1x | 420x270 |
| hero: tablet (420-767px)| Hero fixed image fields | Hero fixed b-tablet 1x | 768x400 |
| hero: desktop (768-1439x)| Hero fixed image fields | Hero fixed c-desktop 1x | 1440x460 |
| hero: large (1440-1919px)| Hero fixed image fields | Hero fixed d-large 1x | 1920x460 |
| hero: oversize (>1920px)| Hero fixed image fields | Hero fixed e-oversize 1x | 2400x460 |
| **para:photo field_image**<br>**para:video field_image**<br>**para:hero field_image**<br>**para:map field_image** | 
| hero: mobile (<419px)| Photo Bleed Images | Photo bleed a-mobile 1x | 420x250 |
| hero: tablet (420-767px)| Photo Bleed Images | Photo bleed b-tablet 1x | 768x420 |
| hero: desktop (768-1439x)| Photo Bleed Images | Photo bleed c-desktop 1x | 1440x800 |
| hero: large (1440-1919px)| Photo Bleed Images | Photo bleed d-large 1x | 1920x800 |
| hero: oversize (>1920px)| Photo Bleed Images | Photo bleed e-oversize 1x | 2400x800 |
| **find** | 
| card: mobile (<419px)| Card Images 3w | Card grid vertical a-mobile 1x | 335x117 |
| card: tablet (420-767px)| Card Images 3w | Card grid vertical b-tablet 1x | 615x215 |
| card: desktop (768-839px)| Card Images 3w | Card grid vertical c-desktop 1x | 670x235 |
| card: desktop (840-1439x)| Card Images 3w | Card grid horizontal c-desktop 1x | 382x134 |
| card: large (1440-1919px)| Card Images 3w | Card grid horizontal d-large 1x | 382x134 |
| card: oversize (>1920px)| Card Images 3w | Card grid horizontal e-oversize 1x | 382x134 |
| **para:column** | this should be a 200x200 circle ?? |
| card: mobile (<419px)| Card Images 3w | Photo bleed a-mobile 1x | 335x117 |
| card: tablet (420-767px)| Card Images 3w | Photo bleed b-tablet 1x | 615x215 |
| card: desktop (768-839px)| Card Images 3w | Photo bleed c-desktop 1x | 670x235 |
| card: desktop (840-1439x)| Card Images 3w | Photo bleed c-desktop 1x | 382x134 |
| card: large (1440-1919px)| Card Images 3w | Photo bleed d-large 1x | 382x134 |
| card: oversize (>1920px)| Card Images 3w | Photo bleed e-oversize 1x | 382x134 |
| **post:field_thumbnail(feature)** |  |
| card: mobile (<419px)| Featured Images | Featured image a-mobile 1x | 335x350 |
| card: tablet (420-767px)| Featured Images | Featured image b-tablet 1x | 614x350 |
| card: desktop (768-839px)| Featured Images | Featured image c-desktop 1x | 671x388 |
| card: desktop (840-1439x)| Featured Images | Featured image d-full 1x | 586x388 |
| card: large (1440-1919px)| Featured Images | Featured image d-full 1x | 586x388 |
| card: oversize (>1920px)| Featured Images | Featured image d-full 1x | 586x388 |
| **node:person_profile:field_person_profile<br>user:user_picture** |  |
| person: mobile (<839px)| Person Photos | Person Photos a-mobile 1x | 110x110 |
| person: tablet (840-979px)| Person Photos | Person Photos b-tablet 1x | 120x120 |
| person: desktop (980-1279px)| Person Photos | Person Photos c-desktop 1x | 148x148 |
| person: desktop (>1280x)| Person Photos | Person Photos d-full 1x | 173x173 |
| **node:pip:field_program_logo** |  |
| person: mobile (<839px)| Logo Images | logo square a-mobile 1x | 672x672 |
| person: tablet (840-979px)| Logo Images | logo square b-tablet 1x | 783x783 |
| person: desktop (980-1279px)| Logo Images | logo square c-desktop 1x | 360x360 |
| person: desktop (>1280x)| Logo Images | logo square d-full 1x | 360x360 |
