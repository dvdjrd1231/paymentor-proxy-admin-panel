### RotatingServices api interface ###
>Available endpoints
* ```GET /v0/services/:service_id``` Get Service info
* ```POST /v0/services/new``` New Service
* ```POST /v0/services/renew/:service_id``` Renew Service (at the moment it just clears few counters)
* ```GET /v0/services/extend/:service_id/:unixtimestamp``` Set service expiration in the future
* ```GET /v0/services/expand/:service_id/:amount``` Add more proxies to service
* ```GET /v0/services/shrink/:service_id/:amount``` Remove proxies from service
* ```GET /v0/services/cancel/:service_id``` Cancel Service
* ```POST /v0/services/aa/:service_id``` Updates service all ports/ips authentication/authorization
* ```GET /v0/services/blacklist/:blacklist_id/:blacklist_status``` changes blacklist status
* ```GET /v0/services/reboot/:service_id[/hard]``` Reboots service
---
All api calls return json with status code and message.
All api calls must be authenticated with header ```Panel: <token>```

---
Tokens are defined in the site configuration.
```php
const API_AUTHENTICATION = [
    'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx' // whmcs default token
];
```

### Get service info
```GET /v0/services/:service_id```

```Response```
```json
{
  "status": "ok",
  "id": 40,
  "expiration": null,
  "plan_manual_rotate": 1,
  "plan_change_rotate": 1,
  "plan_max_rotate": 123,
  "rotation_counter": 0,
  "ips": [
    {
      "ip": "127.0.0.3",
      "port": 10095
    },
    {
      "ip": "127.0.0.3",
      "port": 10096
    },
    {
      "ip": "127.0.0.3",
      "port": 10097
    },
    {
      "ip": "127.0.0.3",
      "port": 10098
    },
    {
      "ip": "127.0.0.3",
      "port": 10099
    }
  ]
}
```
| field              | type       | description                                                                                                    |
|--------------------|------------|----------------------------------------------------------------------------------------------------------------|
| status             | string     | contains status of the request it can be (ok,error)                                                            |
| id                 | int        | unique id of the service, you will need for next calls bellow                                                  |
| expiration         | ts or null | It indicates when this service will expire, it can be null if service expiration is controlled by your website |
| plan_manual_rotate | int        | Enabled manual rotation or not. It can be 1(enabled) or 0 (disabled)                                           |
| plan_change_rotate | int        | Enabled changing rotation time. It can be 1(enabled) or 0 (disabled)                                           |
| plan_max_rotate    | int        | Sets how many manual rotations can be done (per period)                                                        |
| rotation_counter   | ?int       | Displays how many rotations have been made in this period (warn! can be NULL)                                  |
| ips                | array      | array of assigned proxies                                                                                      |

> ips

| field      | type       | description        |
|------------|------------|--------------------|
| ip         | string     | proxy's ip address |
| port       | int        | proxy's port       |
---
### New Service
```POST /v0/services/new```

| field        | type   | mandatory | description                                                          |
|--------------|--------|-----------|----------------------------------------------------------------------|
| client_id	   | int    | no        | unique user id on your web site                                      |
| plan_tag     | string | yes       | tag of the assigned plan                                             |
| server_tag   | string | no        | Place new service on requested server                                |
| amount       | int    | yes       | amount of proxies to be assigned                                     |
| authorize    | array  | no        | preauthorized ip addresses that does not required username+password  |
| authenticate | array  | no        | array with two elements username + password                          |
| expiration   | int    | no        | unix timestamp when the serve will expire. null here means unlimited |

> authorize

| row        | type   | description  |
|------------|--------|--------------|
| 127.0.0.1  | string | first ip     |
| 127.0.0.3  | string | second ip    |
| 127.0.0.3  | string | third ip     |
> authenticate

| field    | type   | description                       |
|----------|--------|-----------------------------------|
| username | string | username for proxy authentication |
| password | string | password for proxy authentication |

```json
{
    "client_id": 2135740897,
    "plan_tag": "eu-dedicated",
    "server_tag": "server-01",
    "amount": 5,
    "authorize": [
        "127.0.0.1",
        "127.0.0.2"
    ],
    "authenticate": {
        "username": "username1",
        "password": "password1"
    },
    "expiration": 1634323414
}
```
```Response```

```
....See get service info response
```

### set Expiration time
```GET /v0/services/extend/:service_id/:unixtimestamp```

Set service expiration in the future

| field         | type       | mandatory | description                                                         |
|---------------|------------|-----------|---------------------------------------------------------------------|
| service_id    | int        | yes       | service where new proxies will be assigned                          |
| unixtimestamp | int        | yes       | sets the expiration in the future                                   |

```Response```
```
....See get service info response
```

### Add more ips to service
```GET /v0/services/expand/:service_id/:amount```

Requested amount of proxies will be from the same network/pool assigned.
One service can't span on multiple networks/pools
If there are no available network or free proxies, this command will fail

| field         | type       | mandatory | description                                                         |
|---------------|------------|-----------|---------------------------------------------------------------------|
| service_id    | int        | yes       | service where new proxies will be assigned                          |
| amount        | int        | yes       | amount of proxies to be assigned                                    |

```Response```
```
....See get service info response
```

### Remove proxies from service
```GET /v0/services/shrink/:service_id/:amount```

Remove some proxies from service. You can't remove all the proxies from requested service.

| field         | type       | mandatory | description                                                         |
|---------------|------------|-----------|---------------------------------------------------------------------|
| service_id    | int        | yes       | service where new proxies will be assigned                          |
| amount        | int        | yes       | amount of proxies to be assigned                                    |

```Response```
```
....See get service info response
```

### Cancel Service
```GET /v0/services/cancel/:service_id```

| field         | type       | mandatory | description                                                         |
|---------------|------------|-----------|---------------------------------------------------------------------|
| service_id    | int        | yes       | service where new proxies will be assigned                          |

This will stop immediately requested service and unconditionally frees all the assigned resources

```Response```

```json
{
  "status": "ok",
  "description": "Success"
}
```

| field         | type       |  description                                                         |
|---------------|------------|----------------------------------------------------------------------|
| status        | string     | ok or error                                                          |
| description   | string     | Description of the requests status                                   |

### Updates service authentication/authorization
```POST /v0/services/aa/:service_id```

It will update all the assigned ips/ports at once

| field        | type  | description                                                        |
|--------------|-------|--------------------------------------------------------------------|
| authorize    | array | Can be empty array in order to disable auth_ips,no more than 3 ips |
| authenticate | array | Can be empty array in order to disable auth_ips,no more than 3 ips |
for more information see "New service"

### Changes blacklist status
```GET /v0/services/blacklist/:blacklist_id/:blacklist_status```

| field        | type   | mandatory | description                       |
|--------------|--------|-----------|-----------------------------------|
| blacklist_id | int    | yes       | Id of the blacklist               |
| status       | string | yes       | status can be enabled or disabled |

```Response```

```json
{
  "status": "ok",
  "description": "Success"
}
```

| field         | type       |  description                                                         |
|---------------|------------|----------------------------------------------------------------------|
| status        | string     | ok or error                                                          |
| description   | string     | Description of the requests status                                   |

### Reboots instance
```GET /v0/services/reboot/:service_id``` ```GET /v0/services/reboot/:service_id/hard```

Reboots the instance with reboot command, if hard is set, it will reboot the instance with cloud reboot command
