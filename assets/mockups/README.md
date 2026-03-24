# Mockup Templates

This directory contains room mockup template images used for compositing customer artwork.

## Required Templates

### room-1.jpg

**Dimensions:** 2385 x 1590 pixels

**Drop Zone (where artwork is placed):**
- X: 910
- Y: 240
- Width: 586
- Height: 769

**Usage:**
The template mockup generator will resize and crop the customer's final artwork to fit the drop zone using "cover" behavior (like CSS `object-fit: cover`), then composite it onto the template.

## Adding New Templates

To add a new template:

1. Create the room image with a clear area for artwork placement
2. Measure the exact pixel coordinates of the drop zone (x, y, width, height)
3. Add the configuration to `Template_Mockup_Generator::TEMPLATE_CONFIG`

Example configuration:

```php
'room-2' => array(
    'file'      => 'room-2.jpg',
    'width'     => 2400,
    'height'    => 1600,
    'drop_zone' => array(
        'x'      => 800,
        'y'      => 200,
        'width'  => 600,
        'height' => 800,
    ),
),
```

## Image Requirements

- Format: JPEG or PNG
- Recommended resolution: At least 2000px on the longest side
- The drop zone should be a flat, front-facing wall area
- Avoid complex shadows or perspective distortion in the drop zone area
