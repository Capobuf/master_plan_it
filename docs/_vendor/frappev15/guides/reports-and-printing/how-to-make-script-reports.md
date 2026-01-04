You can create tabulated reports using server side scripts by creating a new Report.

> Note: You will need Administrator Permissions for this.

Since these reports give you unrestricted access via Python scripts, they can only be created by Administrators. The script part of the report becomes a part of the repository of the application. If you have not created an app, [read this](https://frappe.io/docs/v14/user/en/guides/app-development/).

> Note: You must be in [Developer Mode](https://frappe.io/docs/v14/user/en/guides/app-development/how-enable-developer-mode-in-frappe) to do this

### 1. Create a new Report

1. Set Report Type as "Script Report"
2. Set "Is Standard" as "Yes"
3. Select the Module in which you want to add this report
4. In the module folder (for example if it is Accounts in ERPnext the folder will be `erpnext/accounts/report/[report-name]`) you will see that templates for the report files will be created.
5. In the `.js` file, you can set filters for the reports
6. In the `.py` file, you can write the script that will generate the report

### 2. Add Filters

You can add filters in the `.js`. See an example below:

frappe.query\_reports["Accounts Receivable"] = {
"filters": [
{
"fieldname":"company",
"label": \_\_("Company"),
"fieldtype": "Link",
"options": "Company",
"default": frappe.defaults.get\_user\_default("company")
},
]
}

1. These properties are the same as you would set in a DocField in a DocType

### 3. Add the Script

In the `.py` file you can add the script for generating the report.

1. In the `execute` method, two lists `columns` and `data` are returned
2. Columns must be a list of dictionaries containing fields like fieldname, label, fieldtype, options,width. For example:

```
columns = [{
 "fieldname": "account",
 "label": _("Account"),
 "fieldtype": "Link",
 "options": "Account",
 "width": 300
},
{
 "fieldname": "currency",
 "label": _("Currency"),
 "fieldtype": "Link",
 "options": "Currency",
}]
```

3. You can use all server side modules to build your report.
4. For example see existing reports. ([Balance Sheet](https://github.com/frappe/erpnext/blob/develop/erpnext/accounts/report/balance_sheet/balance_sheet.py))

### 4. Add link for your report on the module page

1. In the module folder (for example if it is Accounts in ERPNext the folder will be `erpnext/config/accounts.py`) you will see labels and items for various sections. The new report can be added in the item list as show in the example:

```
def get_data():
 return [{
 "label": _("Accounting Statements"),
 "items": [{
 "type": "report",
 "name": "Balance Sheet",
 "doctype": "GL Entry",
 "is_query_report": True
 }]
 }]
```
