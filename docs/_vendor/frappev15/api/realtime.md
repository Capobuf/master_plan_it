Frappe ships with an API for realtime events based on [socket.io](https://socket.io/). Since socket.io needs a Node server to run, we run a Node process in parallel to the main web server.

## frappe.realtime.on

To listen to realtime events on the client (browser), you can use the `frappe.realtime.on` method:

```
frappe.realtime.on('event_name', (data) => {
 console.log(data)
})
```

## frappe.realtime.off

Stop listening to an event you have subscribed to:

```
frappe.realtime.off('event_name')
```

## frappe.publish\_realtime

To publish a realtime event from the server, you can use the `frappe.publish_realtime` method:

```
frappe.publish_realtime('event_name', data={'key': 'value'})
```

## frappe.publish\_progress

You can use this method to show a progress bar in a dialog:

```
frappe.publish_progress(25, title='Some title', description='Some description')
```
