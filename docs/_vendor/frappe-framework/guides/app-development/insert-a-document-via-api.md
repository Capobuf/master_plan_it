You can insert documents via a script using the `frappe.get_doc` method

### Examples:

#### 1. Insert a ToDo

todo = frappe.get\_doc({"doctype":"ToDo", "description": "test"})
todo.insert()

---

#### 2. Insert without the user's permissions being checked:

todo = frappe.get\_doc({"doctype":"ToDo", "description": "test"})
todo.insert(ignore\_permissions = True)

---

#### 3. Submit after inserting

todo = frappe.get\_doc({"doctype":"ToDo", "description": "test"})
todo.insert(ignore\_permissions=True)
todo.submit()

---

#### 4. Insert a document on saving of another document

class MyType(Document):
def on\_update(self):
todo = frappe.get\_doc({"doctype":"ToDo", "description": "test"})
todo.insert()

---

#### 5. Insert a document with child tables:

sales\_order = frappe.get\_doc({
"doctype": "Sales Order",
"company": "\_Test Company",
"customer": "\_Test Customer",
"delivery\_date": "2013-02-23",
"sales\_order\_details": [
{
"item\_code": "\_Test Item Home Desktop 100",
"qty": 10.0,
"rate": 100.0,
"warehouse": "\_Test Warehouse - \_TC"
}
]
})
sales\_order.insert()
