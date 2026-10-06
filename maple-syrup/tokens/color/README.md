# Color design tokens

Generally speaking `500` is considered the base shade of a color.

## V1 -> V2 update
In v2.0, Maple Syrup introduced a few more colours as the base palette is modelled from [Tailwind CSS' default palette](https://tailwindcss.com/docs/customizing-colors#default-color-palette).

Here is the update path from Maple Syrup v1.x to v2.0:

```json
{
  "existing color map": {
    "red": "red",
    "orange": "orange",
    "yellow": "yellow",
    "army": "lime",
    "green": "emerald",
    "teal": "teal",
    "blue": "sky",
    "ocean": "indigo",
    "purple": "violet",
    "coral": "fuchsia",
    "pacific": "cyan",
    "pink": "pink"
  },
  "new": [
    "amber",
    "green",
    "cyan",
    "blue",
    "purple",
    "rose"
  ],
  "proposed": {
    "blue": "cobalt",
    "sky": "blue"
  }
}
```

As far as opacities go, `400` is no longer the default, as we've expanded to a full 11-shade palette. `500` is the default "middle" shade for a colour. The shades range as such: `50`, `100`, `200`, `300`, `400`, `500`, `600`, `700`, `800`, `900`, `950`. 
