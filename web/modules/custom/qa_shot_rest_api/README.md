# QAShot REST API
- [Roadmap](#roadmap)
- [Overview](#overview)
- [Bugs](#bugs)
- [Notes](#notes)
- [Todos](#todos)
- [Endpoints for CRUD](#endpoints-for-crud)
    - [GET](#get)
    - [POST](#post)
    - [DELETE](#delete)
    - [PATCH](#patch)
- [Endpoints for custom functionalities](#endpoints-for-custom-functionalities)
- [Changelog](#changelog)

## Overview

The QAShot REST API exposes entities of the custom type 'QAShot Test'. The following modules were used when creating it:

- Drupal 8.4.0 Core REST module
- Drupal 8.4.0 Core CORS
    - See: [services.cors.yml](../../../sites/default/services.cors.yml)
- [RestUI](https://www.drupal.org/project/restui) (Dev only)

To use the API, the user (managed by drupal) has to have the role 'Rest API User'.

## Bugs

- ```¯\_(ツ)_/¯```

## Notes
- The API underwent a big refactor to be better compatible with ReactJS. The Core way of serializing content is therefore not supported.

## Todos

- [ ] Permission fixes (should be done, but needs testing)
- [ ] Expose backstop functionality through the API (run, image paths, configs, etc)

## Endpoints for CRUD


The following methods are supported by the API:

### GET

URL: ```<site>/api/rest/v1/qa_shot_test/{qa_shot_test}?_format=json```

- Get one item with the specified ID
- Throws exceptions when ID is invalid or the entity does not exist
- Implementation notes:
    - Defined by a custom RestResource.

URL: ```<site>/api/rest/v1/test_list?_format=json```

- Get a list of entities
- Implementation notes:
    - Defined by a 'Rest Export' view
- URL parameters:
    - type=```a_b|before_after``` (requested test entity type)
    - page=1 (page number)
    - limit=10 (number of items per page)
- Note:
    - The URL is different here, because of stupid errors.

### POST

URL: ```<site>/api/rest/v1/qa_shot_test?_format=json```

- Create a new entity
- The following headers are needed for the request to be accepted:
    - X-CSRF-Token: <the token>
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- To get the X-CSRF-Token, you need to send a separate GET request
    - URL: ```<site>/session/token```
- Example request body
```json
{
    "user_id": "5",
    "type": "a_b", // or "before_after"
    "name": "RestAPI Post test",
    "field_scenario": [
        {
            "label": "Google",
            "referenceUrl": " [http://www.google.com](http://www.google.com)", //(only at a_b test)
            "testUrl": "http://www.google.hu"
        }
    ],
    "field_viewport": [
        {
            "name": "Desktop",
            "width": "1366",
            "height": "768"
        }
    ],
    "field_tester_engine": "phantomjs",
    "field_tag": [
        "tag1",
        "tag2",
        "tag3"
    ],
    "selectors_to_hide": [
        ".class-to-hide1",
        ".class-to-hide2",
        ".class-to-hide3"
    ],
    "selectors_to_remove": [
        ".class-to-remove1",
        ".class-to-remove2",
        ".class-to-remove3"
    ],
    "field_diff_color": "ff00ff"
}
```
URL: ```<site>/api/rest/v1/last_modification?_format=json```

- Returns the new entity if there was modification, and returns those ids which wasn't modified
- The following headers are needed for the request to be accepted:
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- Example request body
```json
{
 "entities": [
  {
   "tid": 1,
   "changed": 1502118366
  },
  {
   "tid": 2,
   "changed": 1502118366
  }
 ]
}
```
URL: ```<site>/api/rest/v1/queue_status?_format=json```

- Return the queue's state for the selected items.
- The following headers are needed for the request to be accepted:
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- Example request body
```json
{
 "tids": [
   1,
   2,
   3
  ]
}
```
URL: ```<site>/api/rest/v1/force_run?_format=json```

- It will run the selected queue item immediately
- The following headers are needed for the request to be accepted:
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- Example request body
```json
{
  "id": 1,
  "frontend_url": "http://localhost:8080/path/to/test/view"
}
```
URL: ```<site>/api/rest/v1/login?_format=json```

- Use this to test your authorization data
- The following headers are needed for the request to be accepted:
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- Example request body
```json
 {}
```
### DELETE

URL: ```<site>/api/rest/v1/qa_shot_test/{qa_shot_test}?_format=json```

- Delete an entity.
- The following headers are needed for the request to be accepted:
    - X-CSRF-Token: <the token>
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- To get the X-CSRF-Token, you need to send a separate GET request
    - URL: ```<site>/session/token```

### PATCH

URL: ```<site>/api/rest/v1/qa_shot_test/{qa_shot_test}?_format=json```

- Update an existing entity
- The following headers are needed for the request to be accepted:
    - X-CSRF-Token: <the token>
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- To get the X-CSRF-Token, you need to send a separate GET request
    - URL: ```<site>/session/token```
- Example request body (this is the minimal required data)
```json
{
    "name": "RestAPI PATCH test",
    "type": "a_b",
    "field_scenario": [
        {
            "label": "Google",
            "referenceUrl": "http://www.google.com",
            "testUrl": "http://www.google.hu"
        }
    ],
    "field_viewport": [
        {
            "name": "Desktop",
            "width": "1366",
            "height": "768"
        }
    ]
}
```

## Endpoints for custom functionalities

The API has these additional endpoints

### Queue Tests

URL: ```<site>/api/rest/v1/qa_shot_test/{qa_shot_test}/queue?_format=json```

- Method: POST
- Start a test according to the POST data
- The following headers are needed for the request to be accepted:
    - X-CSRF-Token: <the token>
    - Content-Type: application/json
    - Authorization: <the username+password encoded>
- To get the X-CSRF-Token, you need to send a separate GET request
    - URL: ```<site>/session/token```
- Example request body (for a test in the 'Before/After' bundle)
```json
{
    "test_stage": "after",
    "type": "a_b",
    "frontend_url": "http://localhost:8080/path/to/test/view"
}
```
If no applicable stage is present, send "" (empty string).

- Explanation:
    - With this example a 'Before/After' type of test will be started at the 'After' stage.
    - In BackstopJS this translates to running the 'Test' command.
- BundleStage map
```
'a_b' => NULL,
'before_after' => [
    'before',
    'after',
],
```
Explanation: For a test with the bundle (type) ```a_b```, send stage as empty. For a test with the ```before_after``` bundle, you can send 'before' or 'after' (note: One per request) as the ```test_stage``` parameter.
- `field_tester_engine` can be: ```phantomjs``` or ```slimerjs```
- `type` can be: ```a_b``` or ```before_after```
- Notes:
    - With the current implementation the request can take a while to finish..
    - If the implementation of a runner queue is done (See: QAS-5), this will return immediately.