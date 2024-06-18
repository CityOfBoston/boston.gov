# swiftype-php

A PHP client for [Swiftype](http://swiftype.com), a search and autocomplete API for developers.

## Example usage

```php
require 'swiftype.php';

$client = new \Swiftype\SwiftypeClient('your@email.com', 'password', 'api_key');

print_r($client->create_engine('library'));

print_r($client->create_document_type('library', 'books'));

print_r($client->create_document('library', 'books', array(
	'external_id' => '1',
	'fields' => array(
		array(
			'name' => 'title',
			'value' => 'The Art of Community',
			'type' => 'string'
		),
		array(
			'name' => 'author',
			'value' => 'Jono Bacon',
			'type' => 'enum'
		)
	)
)));

print_r($client->documents('library', 'books'));
```

### Documentation

The library should conform to the documentation found [here](http://swiftype.com/documentation/overview).

#### __construct([username String], [password String], [api_key String], [host String], [api_base_path String])
The constructor for the SwiftypeClient object. Set your authentication information here. You can supply either an API key or a username (email) and password combination.

`$client = new \Swiftype\SwiftypeClient('your@email.com', 'password', 'api_key');`

#### engines()
Returns all your engines

`$client->engines();`

#### engine(engine_id String)
Returns a specific engine.

`$client->engine('library');`

#### create_engine(engine_id String)
Creates a new engine

`$client->create_engine('library');`

#### destroy_engine(engine_id String)
Destroys an engine

`$client->destroy_engine('library');`

#### document_types(engine_id String)
Returns a list of all the document types for a certain engine

`$client->document_types('library');`

#### document_type(engine_id String, document_type_id String)
Fetches a specific document_type.

`$client->document_type('library', 'books');`

#### create_document_type(engine_id String, document_type_id String)
Creates a document type for a specific engine.

`$client->create_document_type('library', 'books');`

#### destroy_document_type(engine_id String, document_type_id String)
Destroys a document type.

`$client->destroy_document_type('library', 'books');`

#### documents(engine_id String, document_type_id String)
Returns all documents for a certain engine and document type.

`$client->documents('library', 'books');`

#### document(engine_id String, document_type_id String, document_id String)
Returns a specific document.

`$client->document('library', 'books', '1');`

#### create_document(engine_id String, document_type_id String, document Array)
Creates a document. A document is an associative array containing an `external_id` and a number of `fields`. See [this](http://swiftype.com/documentation/overview# field_types) for more information on fields and types.

```php
$client->create_document('library', 'books', array(
    'external_id' => '1',
    'fields' => array(
        array(
            'name' => 'title',
            'value' => 'The Art of Community',
            'type' => 'string'
        ),
        array(
            'name' => 'author',
            'value' => 'Bono Jacon',
            'type' => 'enum'
        )
    )
));
```

#### create_or_update_document(engine_id String, document_type_id String, document Array)
Same as `create_document`, except it updates an existing document if there is one.

```php
$client->create_or_update_document('library', 'books', array(
    'external_id' => '1',
    'fields' => array(
        array(
            'name' => 'author',
            'value' => 'Jono Blargon',
            'type' => 'enum'
        )
    )
));
```

#### update_document(engine_id String, document_type_id String, document_id String, fields Array)
Updates a single document with the specified `document_id`.

```php
$client->update_document('library', 'books', '1', array('author' => 'Jorbo Bacon'));
```

#### update_documents(engine_id String, document_type_id String, documents Array)
Batch operation for updating documents. `documents` is simply an array containing arrays of the same type that we supplied to the `create_document`method.

```php
$client->update_documents('library', 'books', array(
    array(
        'external_id' => '1',
        'fields' => array(
            'name' => 'author',
            'value' => 'Jono Bacon',
        )
    )
));
```

#### destroy_document(engine_id String, document_type_id String, document_id String)
Destroys a document.

`$client->destroy_document('library', 'books', '1');`

#### destroy_documents(engine_id String, document_type_id String, document_ids Array)
Destroy documents in bulk. `document_ids` is a simple array containing the `external_id`s of the documents you wish to destroy.

`$client->destroy_documents('library', 'books', array('1', '2'));`

#### search(engine_id String, [document_type_id String], query String, [options Array])
If you do not supply a `document_type_id`, `search` searches through the specified engine to find a document type that matches the query. If a `document_type_id` **is** supplied, then `search` searches through that particular document type in that engine for a document that matches the query.

To see what options are available, [see the documentation](http://swiftype.com/documentation/searching).

```php
$client->search('library', 'books', 'community', array(
    'per_page' => 5
));
```

#### suggest(engine_id String, query String, [options Array])
Used for autocompletion. [See the documentation](http://swiftype.com/documentation/autocomplete) for more information.

```php
$client->suggest('library', 'Bacon', array(
    'search_fields' => 'author'
));
```
