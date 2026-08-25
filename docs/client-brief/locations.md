### Locations api interface ###
>Available endpoints

* ```POST /v0/locations/new``` New 
* ```POST /v0/locations/update/:location_tag``` Update Location
* ```GET /v0/locations/delete/:location_id``` Delete Location
* ```GET /v0/locations/:location_tag``` Get Location
* ```GET /v0/locations/status/:location_id/:status``` Update Location Status
* ```GET /v0/locations/list``` List Locations
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
### New Location

```POST /v0/locations/new```

| field        | type   | mandatory | description                     |
|--------------|--------|-----------|---------------------------------|
| continent	   | string | yes       | Location continent              |
| country      | string | yes       | Location country code           |
| country_name | string | yes       | Location country name           |
| state        | int    | yes       | Location state                  |
| city         | array  | yes       | Location city                   |
| do           | array  | yes       | array with 3 priority locations |
| linode       | array  | yes       | array with 3 priority locations |
| vultr        | array  | yes       | array with 3 priority locations |
| region_code  | string | yes       | Location region code            |
| zip_code     | string | no        | Location zip code               |

```json
{
  "continent": "Europe",
  "country": "DE",
  "country_name": "Germany",
  "state": "",
  "city": "Bonn",
  "do": {
    "prio1": "12345",
    "prio2": "12345",
    "prio3": "12345"
  },
  "linode": {
    "prio1": "12345",
    "prio2": "12345",
    "prio3": "12345"
  },
  "vultr": {
    "prio1": "12345",
    "prio2": "12345",
    "prio3": "12345"
  },
  "region_code": "BR-Rj",
  "zip_code": ""
}
```

```Response```
```json
{
  "status": "ok",
  "description": "Location created"
}

```
### Update Location

```POST /v0/locations/update/:location_tag```

| field        | type   | mandatory | description                     |
|--------------|--------|-----------|---------------------------------|
| continent	   | string | yes       | Location continent              |
| country      | string | yes       | Location country code           |
| country_name | string | yes       | Location country name           |
| state        | int    | yes       | Location state                  |
| city         | array  | yes       | Location city                   |
| do           | array  | yes       | array with 3 priority locations |
| linode       | array  | yes       | array with 3 priority locations |
| vultr        | array  | yes       | array with 3 priority locations |
| region_code  | string | yes       | Location region code            |
| zip_code     | string | no        | Location zip code               |

```json
{
  "continent": "Europe",
  "country": "DE",
  "country_name": "Germany",
  "state": "",
  "city": "Bonn",
  "do": {
    "prio1": "3211",
    "prio2": "3214",
    "prio3": "sgp1"
  },
  "linode": {
    "prio1": "12345",
    "prio2": "12345",
    "prio3": "12345"
  },
  "vultr": {
    "prio1": "12345",
    "prio2": "12345",
    "prio3": "12345"
  },
  "region_code": "BR-Rj",
  "zip_code": ""
}
```

```Response```
```json
{
  "status": "ok",
  "description": "Location updated"
}

```

### Delete Location
```GET /v0/locations/delete/:location_tag```

```Reponse```
```json
{
  "status": "ok",
  "description": "Location removed"
}
```

### Get Location
```GET /v0/locations/:location_tag```

```Reponse```
```json
{
  "status": "ok",
  "id": 34,
  "continent": "Africa",
  "country": "DJ",
  "country_name": "Djibouti",
  "state": "Djibouti Region",
  "city": "Djibouti City",
  "tag": "dj-dji-1",
  "total": 1,
  "used": 0,
  "free": 1,
  "do": {
    "location_id": 34,
    "class": "do",
    "prio1": "sgp1",
    "prio2": "blr1",
    "prio3": "lon1"
  },
  "linode": {
    "location_id": 34,
    "class": "linode",
    "prio1": "ap-west",
    "prio2": "ap-south",
    "prio3": "us-southeast"
  },
  "vultr": {
    "location_id": 34,
    "class": "vultr",
    "prio1": "sgp",
    "prio2": "ams",
    "prio3": "sto"
  }
}
```

### Set Location status
```GET /v0/locations/status/:location_tag/:status```
* status is one of the following:
  * ```enabled```
  * ```disabled```

```Reponse```
```json
{
  "status": "ok",
  "description": "Location status updated"
}
```

### List Locations
```GET /v0/locations/list```

get parameters

| field | type   | mandatory | description |
|-------|--------|-----------|-------------|
| page	 | string | yes       | Page        |


```Reponse```
```json
{
  "status": "ok",
  "locations": [
    {
      "id": 23,
      "continent": "Europe",
      "country": "UK",
      "country_name": "United Kingdom",
      "state": "",
      "city": "London",
      "tag": "uk-lon-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 25,
      "continent": "Europe",
      "country": "UK",
      "country_name": "United Kingdom",
      "state": "",
      "city": "London",
      "tag": "uk-lon-2",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 2,
      "continent": "North America",
      "country": "CA",
      "country_name": "Canada",
      "state": "Alberta",
      "city": "Calgary",
      "tag": "ca-cal-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 16,
      "continent": "North America",
      "country": "CA",
      "country_name": "Canada",
      "state": "Ontario",
      "city": "Toronto",
      "tag": "ca-tor-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 17,
      "continent": "North America",
      "country": "CA",
      "country_name": "Canada",
      "state": "Manitoba",
      "city": "Winnipeg",
      "tag": "ca-win-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 1,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Virginia",
      "city": "Ashburn",
      "tag": "us-ash-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 3,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Ilinois",
      "city": "Chicago",
      "tag": "us-chi-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 4,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Texas",
      "city": "Dallas",
      "tag": "us-dal-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 5,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Colorado",
      "city": "Denver",
      "tag": "us-den-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 12,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Miami",
      "city": "Florida",
      "tag": "us-flo-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 6,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "California",
      "city": "Fremont",
      "tag": "us-fre-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 8,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "California",
      "city": "Fremont",
      "tag": "us-fre-2",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 9,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Hawaii",
      "city": "Honolulu",
      "tag": "us-hon-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 10,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Missouri",
      "city": "Kansas City",
      "tag": "us-kan-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 11,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "California",
      "city": "Los Angeles",
      "tag": "us-los-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    },
    {
      "id": 14,
      "continent": "North America",
      "country": "US",
      "country_name": "United States",
      "state": "Arizona",
      "city": "Phoenix",
      "tag": "us-pho-1",
      "total": 1,
      "used": 0,
      "free": 1,
      "status": "enabled"
    }
  ]
}
```
