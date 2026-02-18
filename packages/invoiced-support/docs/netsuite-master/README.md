# Invoiced Integration for NetSuite

![CI](https://github.com/Invoiced/netsuite/workflows/CI/badge.svg)

This project contains the SuiteBundle for the Invoiced-NetSuite integration that customers can install on NetSuite.

## Developing

### Resources

- [NSAPI reference](https://docs.oracle.com/cloud/latest/netsuitecs_gs/NSAPI/NSAPI.pdf)
- [Schema Browser](https://www.netsuite.com/help/helpcenter/en_US/srbrowser/Browser2016_2/script/record/account.html)
- [Usefull link for Netsuite record type Ids representation](http://www.netsuiterp.com/2019/05/internal-ids-for-netsuite-record-types.html)

### Code Style

In order to maintain a high level of quality in the codebase we use [jshint](http://jshint.com/) and [js-beautify](https://github.com/beautify-web/js-beautify). They can be ran with the following commands:

	./beautify
	jshint .bundle

### Testing against staging

Go to Customization / Scripts / INVOICED INTEGRATION / Parameters / New parameter
![Alt text](/../Screenshots/Screenshots/Is-staging.png?raw=true)

### Build

```
npm run build
```

### Unit tests

run (source should be built prior run)
```
npm run test
```


## Deploying

### Building the bundle

1. Clone the project

```
git clone git@github.com:Invoiced/netsuite.git
cd netsuite
```

2. Install npm dependencies

```
npm install
```

3. Build the project. The compiled source code will be located in the `src/` folder. A `src.zip` file is also created for upload to NetSuite.

```
npm run build
```


### Building release candidate
1. Build the application
2. Upload built the files through file cabinet
3. Apply all appropriate changes to the bundle in the QA Trailing account



### Saving to NetSuite

#### 1. Create entity custom fields

On NetSuite go to **Customization** / **List, Records & Fields** / **Entity Fields** / **New** and create the fields [described here](config/Fields/Entity/).

#### 2. Create transaction body fields

On NetSuite go to **Customization** / **List, Records & Fields** / **Transaction Body Fields** / **New** and create the fields [described here](config/Fields/TransactionBody/).

#### 3. Create lists

On NetSuite go to **Customization** / **List, Records & Fields** / **Lists** / **New** and create the lists [described here](config/Lists/).

#### 4. Create record types

On NetSuite go to **Customization** / **List, Records & Fields** / **Record Types** / **New** and create the record types [described here](config/Records/).

#### 5. Upload scripts

* Go to NetSuite File Cabinet (Use NetSuite search for navigation)

* Click on Advanced add

![Alt text](/../Screenshots/Screenshots/FileCabinet.png?raw=true)

* Upload zip archive using following inputs:

![Alt text](/../Screenshots/Screenshots/UploadScripts.png?raw=true)

* Create script according to config/Scripts/customscript_invd_integration.xml

#Important!

Each script deployment should have all Roles checkbox enabled!

* Repeat for every XML file in the config/Scripts

* Create appropriateroes 
* records from the config/Records
* fields from the config/Fields
* lists from the config/Lists

#### 6. Create roles 

On NetSuite go to **Setup** / **Users & Roles** / **Manage Roles** / **New** and create the roles as [described here](config/Roles).

![Alt text](/../Screenshots/Screenshots/InvoicedRole1.png?raw=true)

![Alt text](/../Screenshots/Screenshots/InvoicedRole2.png?raw=true)

![Alt text](/../Screenshots/Screenshots/InvoicedRole3.png?raw=true)

![Alt text](/../Screenshots/Screenshots/InvoicedRole4.png?raw=true)

### Usage

* Go to Setup / Integration / Manage Integrations

create new integration, save consumer keys

add consumer keys to the Invoiced project

* Go to Setup / Users/Roles / Access Tokens

Create new access token for the application and role above

* Go to Setup / Company / General Preferences

* Open Custom Preferences tab

Input invoiced API key

![Alt text](/../Screenshots/Screenshots/CompanySettings.png?raw=true)
