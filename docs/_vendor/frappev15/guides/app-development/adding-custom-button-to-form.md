To create a custom button on your form, you need to edit the javascript file associated to your doctype. For example, If you want to add a custom button to User form then you must edit `user.js`.

In this file, you need to write a new method `add_custom_button` which should add a button to your form.

#### Function Signature for `add_custom_button(...)`

frm.add\_custom\_button(\_\_(buttonName), function(){
//perform desired action such as routing to new form or fetching etc.
}, \_\_(groupName));

#### Example-1: Adding a button to User form

We should edit `frappe\core\doctype\user\user.js`

frappe.ui.form.on('User', {
refresh: function(frm) {
...
frm.add\_custom\_button(\_\_('Get User Email Address'), function(){
frappe.msgprint(frm.doc.email);
}, \_\_("Utilities"));
...
}
});

You should be seeing a button on user form as shown below,
