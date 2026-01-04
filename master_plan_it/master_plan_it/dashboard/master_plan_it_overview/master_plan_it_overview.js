frappe.provide("frappe.dashboards");

frappe.dashboards["Master Plan IT Overview"] = {
    filters: [
        {
            fieldname: 'year',
            label: __('Year'),
            fieldtype: 'Link',
            options: 'MPIT Year',
            default: frappe.defaults.get_user_default('year')
        },
        {
            fieldname: 'cost_center',
            label: __('Cost Center'),
            fieldtype: 'Link',
            options: 'MPIT Cost Center'
        }
    ]
};
