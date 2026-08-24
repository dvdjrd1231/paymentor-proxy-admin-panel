### Tunnels api interface ###
>Available endpoints

* ```POST /v0/tunnes/new``` New Tunnel
* ```POST /v0/tunnels/update/:tunnel_id/class/:class``` Update Tunnel
* ```GET /v0/tunnels/delete/:tunnel_id/class/:class``` Delete Tunnel
* ```GET /v0/tunnels/:tunnel_id/class/:class``` Get Tunnel
* ```GET /v0/tunnels/info/:tunnel_id/class/:class``` Get Tunnel Info From Provider 
* ```GET /v0/tunnels/status/:tunnel_id/class/:class/:status``` Update Tunnel Status
* ```GET /v0/tunnels/list``` List Tunnels
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

### New Tunnel

```POST /v0/tunnes/new```

| field        | type   | mandatory | description                    |
|--------------|--------|-----------|--------------------------------|
| email	       | string | yes       | Email of the tunnel account    |
| username     | string | yes       | Username of the tunnel account |
| user_id      | string | yes       | User for api request           |
| update_key   | int    | yes       | Password/Key for api request   |
| tunnel_id    | array  | yes       | Unique TunnelID                |
| class        | array  | yes       | Class that handles this tunnel |
| location_tag | string | yes       | Location tag for this tunnel   |

```json
{
  "email": "datafast07@chatapi.digital",
  "username": "datafast07",
  "user_id": "tb61928201298942.72793030",
  "update_key": "g9zcOYYZThswiOsn",
  "tunnel_id": "674187",
  "class": "TunnelBroker"
}
```

```Response```
```json
{
  "status": "ok",
  "description": "Tunnel created"
}

```
### Update tunnel

```POST /v0/tunnes/update/:tunnel_id/class/:class```
* tunnel_id is id of the tunnel from the provider
* class is provider name
* you can't change location_tag

| field        | type   | mandatory | description                    |
|--------------|--------|-----------|--------------------------------|
| email	       | string | yes       | Email of the tunnel account    |
| username     | string | yes       | Username of the tunnel account |
| user_id      | string | yes       | User for api request           |
| update_key   | int    | yes       | Password/Key for api request   |
| tunnel_id    | array  | yes       | Unique TunnelID                |
| class        | array  | yes       | Class that handles this tunnel |


```json
{
  "email": "datafast07@chatapi.digital",
  "username": "datafast07",
  "user_id": "tb61928201298942.72793030",
  "update_key": "g9zcOYYZThswiOsn",
  "tunnel_id": "674187",
  "class": "TunnelBroker"
}
```

```Response```
```json
{
  "status": "ok",
  "description": "Tunnel updated"
}

```

### Delete tunnel
```GET /v0/tunnels/delete/:tunnel_id/class/:class```
* tunnel_id is id of the tunnel from the provider
* class is provider name

```Reponse```
```json
{
  "status": "ok",
  "description": "Success"
}
```


### Get tunnel
```GET /v0/tunnels/:tunnel_id/class/:class```
* tunnel_id is id of the tunnel from the provider
* class is provider name
*
```Reponse```
```json
{
    "status": "ok",
    "tunnels": [
        {
            "id": 2,
            "tunnel_id": 765769,
            "location_id": 1,
            "service_id": null,
            "local_ip": null,
            "remote_ip": "216.66.22.2",
            "network48": "2001:470:e484::\/48",
            "network64": "2001:470:8:5d7::\/64",
            "email": "datafasttest0001@test.netfiretec.com",
            "username": "datafasttest001",
            "user_id": "tb6282626b6d5fe8.55272513",
            "update_key": "ba-DD0O6lzCPNKH5",
            "status": "free",
            "class": "TunnelBroker",
            "country": "US",
            "city": "Ashburn",
            "continent": "North America",
            "state": "Virginia",
            "tag": "us-ash-1"
        }
    ]
}
```

### Get tunnel info from provider
```GET /v0/tunnels/info/:tunnel_id/class/:class```
* tunnel_id is id of the tunnel from the provider
* class is provider name
*
```Reponse```
```json
{
  "status": "ok",
  "tunnel_id": "674187",
  "local_ip": "45.76.246.143",
  "remote_ip": "64.71.156.86",
  "network48": "2600:70ff:f9fa::\/48",
  "network64": "2001:470:1f1f:2dd::\/64"
}
```


### Set tunnel status
```GET /v0/tunnels/status/:tunnel_id/class/:class/:status```
* tunnel_id is id of the tunnel from the provider
* class is provider name
* status is one of the following:
  * ```disabled```
  * ```free```

```Reponse```
```json
{
  "status": "ok",
  "description": "Tunnel is disabled"
}
```

```Reponse```
```json
{
  "status": "ok",
  "tunnel_id": "674187",
  "local_ip": "45.76.246.143",
  "remote_ip": "64.71.156.86",
  "network48": "2600:70ff:f9fa::\/48",
  "network64": "2001:470:1f1f:2dd::\/64"
}
```

### List tunnels
```GET /v0/tunnels/list```

get parameters

| field | type   | mandatory | description |
|-------|--------|-----------|-------------|
| page	 | string | yes       | Page        |


```Reponse```
```json
{
    "status": "ok",
    "tunnels": [
        {
            "id": 2,
            "tunnel_id": 765769,
            "location_id": 1,
            "service_id": null,
            "local_ip": null,
            "remote_ip": "216.66.22.2",
            "network48": "2001:470:e484::\/48",
            "network64": "2001:470:8:5d7::\/64",
            "email": "datafasttest0001@test.netfiretec.com",
            "username": "datafasttest001",
            "user_id": "tb6282626b6d5fe8.55272513",
            "update_key": "ba-DD0O6lzCPNKH5",
            "status": "free",
            "class": "TunnelBroker",
            "country": "US",
            "city": "Ashburn",
            "continent": "North America",
            "state": "Virginia",
            "tag": "us-ash-1"
        }
    ]
}
```
