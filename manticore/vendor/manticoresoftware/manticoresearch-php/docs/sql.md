# SQL
Allows running a SQL command.
For complete reference of payload and response see Manticore's [SQL API](https://docs.manticoresearch.com/latest/html/http_reference/sql.html).
`body` must have `query` parameter with the desired SQL command to be executed.
 
Manticore Search below 3.4 can only execute SELECT commands via `sql`.

```
$params = [
    'body' => [
        'query' => "SELECT * FROM movies where MATCH('@movie_title star trek')"
    ]
];

$response = $client->sql($params);
```
For non-SELECT commands, `mode` must be passed:

```
$params = [
    'mode' => 'raw',
    'body' => [
        'query' => "UPDATE movies set rating=7.0 WHERE id=10"
    ]
];

$response = $client->sql($params);
```
