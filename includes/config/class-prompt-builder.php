<?php
/**
 * Prompt Builder
 *
 * Builds normalized prompts from style + character/situation + background color.
 * Merges layers intelligently (neutral minimizes change; some situations override composition).
 *
 * @package WC_AICC\Config
 */

namespace WC_AICC\Config;

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Prompt Builder class
 */
class Prompt_Builder {

    /**
     * Base identity lines (reference image — not raw user text)
     *
     * @var array
     */
    const IDENTITY_LINES = array(
        'based on the uploaded reference image',
        'preserve the subject identity, recognizable features, and proportions from the reference',
    );

    /**
     * Default negative constraints
     *
     * @var array
     */
    const CONSTRAINTS = array(
        'no text, no watermark, no logo, no captions',
        'no extra duplicate subjects',
        'no gross anatomy distortion',
    );

    /**
     * Per-style definition: core prompt fragments + optional composition hint + extra negatives
     * (Aligned with client style briefs; subject wording generalized from pet-specific lines.)
     *
     * @var array
     */
    const STYLE_DEFINITIONS = array(
        'warhol_grid' => array(
            'core' => array(
                '(masterpiece:1.2), Andy Warhol iconic pop art screen-print portrait',
                'high-end pop art poster design',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate fur or coat into bold graphic shapes and flat color fills',
                'four-panel grid composition in the style of Warhol Marilyn / Queen Elizabeth repeats',
                'four square panels in a two-by-two grid, same portrait repeated',
                'each quadrant with distinct saturated colorways and silkscreen separation',
                'flat ink blocks with slight imperfect registration cues',
                'high contrast palettes, acetone or litho pop texture optional',
                'bold contours, celebrity portrait billboard energy',
                'close-up head and upper chest readable in each panel',
                'playful iconic mass-media attitude',
                'minimal noise inside panels',
            ),
            'composition' => 'four equal quadrants, aligned subject placement in each panel',
            'negative_extra' => array(
                'no text, no watermark, no logo, no magazine masthead',
                'no extra animals',
                'no human figure replacement for the pet subject unless present in reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no photoreal candid photo',
                'no watercolor or oil painterly brush chaos',
                'no single lonely panel unless grid implied',
                'no 3D CGI look',
            ),
        ),
        'watercolor' => array(
            'core' => array(
                '(masterpiece:1.2), (watercolor illustration:1.4)',
                'high-end watercolor painting',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into soft watercolor brush textures',
                'cute stylized watercolor character design',
                'expressive eyes, soft rounded facial features',
                'delicate watercolor shading',
                'fine brush strokes and pigment diffusion',
                'traditional watercolor painting technique',
                'subtle color bleeding and layered washes',
                'close-up head and chest portrait',
                'warm friendly personality expression',
                'ultra refined watercolor illustration quality',
                'storybook watercolor illustration aesthetic',
                'simple clean watercolor paper background',
            ),
            'composition' => 'portrait-oriented composition with breathing room',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no oil painting style',
                'no digital 3D render',
            ),
        ),
        'pop_art' => array(
            'core' => array(
                '(masterpiece:1.2), (pop art illustration:1.4)',
                'high-end pop art portrait',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into bold graphic shapes and flat color areas',
                'iconic pop art character design',
                'large expressive eyes, simplified facial features',
                'bold outlines and strong graphic contrast',
                'vibrant saturated colors',
                'clean vector-like illustration style',
                'bold commercial illustration poster energy',
                'close-up head and chest portrait',
                'playful and energetic personality expression',
                'high contrast color blocking',
                'modern pop art poster style',
                'simple bright graphic background with geometric shapes',
            ),
            'composition' => 'strong graphic composition, centered subject',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
                'no photorealism',
                'no watercolor style',
                'no 3D render',
            ),
        ),
        'pixar' => array(
            'core' => array(
                '(masterpiece:1.2), (pixar style 3D animation:1.4)',
                'high-end animated film character render',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate real coat into stylized soft animated fur or surface',
                'cute stylized character design',
                'large expressive eyes, soft rounded facial features',
                'clean cinematic lighting',
                'smooth detailed fur or coat shading',
                '3D animated film quality rendering',
                'close-up head and chest portrait',
                'warm friendly personality expression',
                'ultra polished animation studio quality',
                'Pixar style character design, modern animated film aesthetic',
                'simple clean studio background',
            ),
            'composition' => 'clear readable pose, portrait-friendly framing',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
                'no distorted faces',
                'no deformed eyes',
                'no low quality render',
                'no blurry details',
            ),
        ),
        'impasto' => array(
            'core' => array(
                '(masterpiece:1.3), (impasto oil painting:1.4)',
                'thick textured oil paint, palette knife strokes',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'translate coat texture into painterly brush texture',
                'close-up head and chest portrait',
                'expressive eyes',
                'heavy layered oil paint, raised ridges, visible brush direction',
                'fine art museum quality',
            ),
            'composition' => 'balanced portrait-friendly composition',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no extra animals',
                'no human features',
                'no breed or species change from the reference',
                'no exaggerated anatomy',
            ),
        ),
        'american_traditional_tattoo' => array(
            'core' => array(
                '(masterpiece:1.2), american traditional tattoo flash illustration',
                'the same subject from the input image',
                'preserve facial structure and markings, coat pattern and proportions from the reference',
                'bold traditional tattoo portrait of the subject head',
                'side view or three-quarter head portrait',
                'strong expressive look, optional dramatic attitude',
                'bold american traditional tattoo style',
                'thick black outlines',
                'heavy black linework',
                'flat color fills',
                'high contrast shapes',
                'limited traditional tattoo color palette',
                'black, red, cream, muted green',
                'classic tattoo flash sheet aesthetic',
                'clean vector-like illustration',
                'minimal shading',
                'decorative tattoo elements around the head',
                'spider webs',
                'spark stars',
                'lightning bolts',
                'clean background',
                'tattoo flash design',
            ),
            'composition' => 'strong central subject, tattoo flash clarity',
            'negative_extra' => array(
                'no text, no watermark, no logo',
                'no low quality render',
                'no blurry details',
            ),
        ),
        'magazine_dogue' => array(
            'skip_identity_lines' => true,
            'allows_cover_text'   => true,
            'core'                   => array(
                'using the uploaded dog photo as the exact facial reference, fur coloration, head shape, ear shape, nose, eyes, expression, and breed characteristics',
                'luxury fashion magazine cover portrait featuring this dog as an elegant high-fashion icon',
                'vertical portrait magazine cover matching the canvas aspect ratio, dog centered, head and upper chest',
                'generous top margin reserved for masthead, large DOGUE masthead fully visible at top with complete uncropped lettering',
                'facing slightly off-camera, no additional headlines or cover text, clean negative space below masthead',
                'oversized black cat-eye sunglasses, elegant silk headscarf tied under the chin, pearl necklace',
                'vintage European luxury styling, sophisticated fashion-editorial aesthetic, confident timeless attitude',
                'flat color backdrop, no props, no texture, minimalist composition',
                'this is NOT a photograph',
                'premium editorial illustration with the appearance of a hand-painted digital portrait',
                'stylized luxury illustration, soft painterly brushwork, visible brushstroke texture',
                'smooth painted fur rendering, simplified editorial shapes, refined color blocking',
                'high-end fashion illustration, minimal realism, premium poster aesthetic, contemporary gallery artwork',
                'clean edges, elegant painted finish',
                'soft studio-inspired lighting, gentle shadows, warm highlights, luxury color grading, minimal contrast, sophisticated editorial mood',
                'preserve exact appearance of the uploaded dog, fur color, facial proportions, eye shape, nose shape, ear shape, breed characteristics',
                'do not humanize facial features',
                'luxury fashion illustration, museum-quality pet portrait, high-end editorial artwork, premium print quality',
                'modern minimalist design, fashion campaign aesthetic',
                'in the style of luxury Vogue editorials, contemporary fashion illustration, digital oil painting',
                'luxury pet portrait artwork, modern editorial design, painted magazine cover, premium gallery print, minimalist luxury branding',
            ),
            'composition' => 'portrait magazine cover, full DOGUE masthead visible with top safe margin, dog centered head and upper chest below masthead',
            'negative_extra' => array(
                'no cropped masthead, no cut-off or clipped DOGUE lettering at top edge',
                'no headlines, captions, or cover lines except the single DOGUE masthead',
                'no watermark, barcode, issue date, or extra typography',
                'no photograph, photoreal snapshot, or camera realism',
                'no extra animals or humans',
                'no cluttered props, scenery, or textured background',
                'no distorted anatomy, deformed eyes, or humanized facial features',
                'no low quality, glitch artifacts, or cheesy HDR',
            ),
        ),
        'royal_legacy' => array(
            'core' => array(
                '(masterpiece:1.2), majestic royal ancestral portrait mural',
                'the same subject from the input image',
                'preserve heraldic likeness, coat markings, stature from reference',
                'dramatic Baroque or Tudor court illumination',
                'sumptuous ermine trims, embroidered silk robes optional',
                'ornate gilt frame vignette without literal heraldic typography',
                'old master glazing, subtle craquelure',
                'warm candlelit key with cool shadow fill balance',
                'close-up ceremonial bust presentation',
                'noble restrained expression grandeur',
                'museum oil or tempera grandeur',
                'rich gemstone color accents subdued',
                'velvet drapery cascade background suggestion',
                'storybook heirloom gravitas',
            ),
            'composition' => 'formal symmetrical crest-like portrait presence',
            'negative_extra' => array(
                'no text, watermark, coat-of-arms lettering',
                'no modern office props',
                'no flat pop vector look',
                'no snapshot flash lighting',
                'no exaggerated grotesque features',
                'no grayscale unless palette chosen later',
                'no cluttered contemporary UI',
                'no muddy texture soup',
                'no distorted anatomy',
            ),
        ),
        'black_studio' => array(
            'skip_identity_lines' => true,
            'core'                => array(
                'premium minimalist pet portrait poster using the uploaded pet photo as the exact facial structure, expression, fur color, markings, eye shape, nose shape, and overall likeness reference',
                'hand-painted digital illustration, Procreate-style artwork',
                'luxury pet portrait aesthetic, soft painterly brushwork, clean edge rendering',
                'realistic fur interpretation rather than photorealism, high-end wall art, contemporary gallery poster design',
                'vertical poster layout 2:3 ratio, pet centered horizontally',
                'only head and upper chest visible, pet occupies approximately 30-40% of total canvas height',
                'large negative space above the pet, portrait in lower third of canvas, symmetrical composition, no tilt',
                'solid matte charcoal black studio background #1A1A1A, no gradients, no textures, no patterns, no scenery, no shadows on background',
                'soft professional studio lighting, subtle highlights in eyes, gentle nose highlights, natural depth',
                'no dramatic contrast, natural golden fur coloration preserved',
                'luxury custom pet portrait brands, modern Scandinavian poster design, premium Etsy pet portrait aesthetic',
                'Procreate digital painting, minimalist gallery wall artwork, clean contemporary illustration',
                'ultra clean, print-ready, elegant, sophisticated, minimal, premium, museum-quality poster appearance',
                'professionally commissioned Procreate painting not a photograph',
                'highly recognizable pet from reference while simplifying fur into refined painterly brush strokes',
            ),
            'composition' => 'vertical 2:3 poster, pet head and upper chest in lower third, wide negative space above for name typography',
            'negative_extra' => array(
                'no collars, bandanas, accessories, clothing',
                'no frames, borders, watermarks, decorative elements, background objects',
                'no extra text beyond the pet name typography',
                'no photographic effects, no 3D rendering',
                'no gradients or textures on background',
                'no scenery, no props',
            ),
        ),
        'whiskey_office' => array(
            'core' => array(
                '(masterpiece:1.25), hyper-realistic cinematic portrait',
                'use the uploaded dog as the exact character reference',
                'preserve the dog facial features, fur color, proportions, expression, and identity',
                'the dog portrayed as a powerful mob boss sitting behind a large executive desk in a luxurious private office',
                'dark wood-paneled office',
                'leather executive chair',
                'crystal whiskey glass on the desk',
                'vintage desk lamp',
                'expensive watch',
                'documents and cigar box on the desk',
                'floor-to-ceiling windows with city lights outside',
                'tailored three-piece Italian suit',
                'white dress shirt, silk tie, gold watch, pocket square',
                'warm cinematic lighting, moody shadows, Godfather-inspired atmosphere',
                'soft rim light, high-end editorial photography',
                '85mm lens, shallow depth of field, eye-level perspective',
                'ultra-detailed fur rendering, professional studio quality',
                'The Godfather mood, Martin Scorsese crime film atmosphere',
                'Vanity Fair celebrity portrait polish, luxury lifestyle photography',
                'confident, powerful, respected, intimidating but charismatic',
                'ultra-realistic, photorealistic, 8K detail, natural fur texture',
                'realistic paws, realistic canine anatomy, cinematic color grading',
            ),
            'composition' => 'eye-level executive desk portrait, mob boss seated behind desk, desk props and city window backdrop framing the subject',
            'negative_extra' => array(
                'no text, watermark, stock photo UI',
                'no cartoon or flat illustration look',
                'no human figure replacing the dog subject',
                'no neon cyberpunk palette',
                'no cluttered legible paperwork or readable documents',
                'no extreme fish-eye distortion',
                'no multiple duplicate subjects',
                'no grotesque caricature or drunken comedy',
                'no modern open-plan bright white office',
                'no low quality render',
                'no distorted canine anatomy',
            ),
        ),
        'toilet' => array(
            'skip_identity_lines' => true,
            'core'                => array(
                '(masterpiece:1.2), (high-end photorealistic pet photography:1.4)',
                'the exact same pet from the uploaded reference image',
                'preserve facial structure, breed traits, fur color, markings, eye color, ear shape and body proportions',
                'do not change the pet\'s identity or distinctive features',
                'pet sitting upright on a closed white toilet seat',
                'front-facing pose, looking directly at the camera',
                'pet holding an open newspaper naturally with both front paws',
                'newspaper positioned across the lower chest and lap',
                'hind paws visible resting near the front edge of the toilet seat',
                'humorous but believable bathroom portrait',
                'clean modern bathroom with white ceramic wall tiles',
                'toilet paper roll mounted on the wall to the left',
                'small potted plant and subtle bathroom accessories in the background',
                'woven laundry basket placed on the floor to the right',
                'soft warm neutral color palette',
                'gentle diffused natural lighting',
                'realistic shadows, reflections and material textures',
                'sharp detailed fur and expressive eyes',
                'centered symmetrical composition',
                'eye-level camera angle',
                'full-body vertical portrait',
                'shallow depth of field with softly blurred background',
                'premium editorial pet photography',
                'playful sophisticated atmosphere',
                'highly realistic photographic result',
            ),
            'composition' => 'centered symmetrical full-body vertical portrait, eye-level camera, pet seated on closed toilet seat holding newspaper',
            'negative_extra' => array(
                'no text, no readable headlines, no watermark, no logo',
                'no extra animals',
                'no humans or visible human body parts',
                'no human hands',
                'no human facial features',
                'no clothing unless present in the uploaded reference image',
                'no breed changes',
                'no fur color changes',
                'no altered markings',
                'no exaggerated anatomy',
                'no oversized head',
                'no distorted face',
                'no deformed eyes',
                'no mismatched eyes',
                'no extra paws or legs',
                'no missing limbs',
                'no fused paws',
                'no malformed newspaper',
                'no floating objects',
                'no open toilet lid behind the pet',
                'no visible waste or toilet humor beyond the setting',
                'no dirty bathroom',
                'no cartoon style',
                'no illustration',
                'no painting',
                'no 3D render',
                'no artificial plastic fur',
                'no fisheye distortion',
                'no extreme wide-angle perspective',
                'no blurry subject',
                'no low-quality render',
            ),
        ),
        'memorial' => array(
            'skip_identity_lines' => true,
            'core'                => array(
                '(masterpiece:1.2), (premium watercolor pet memorial portrait:1.4)',
                '(reference-image identity preservation:1.5)',
                '(facial likeness accuracy:1.5)',
                'the exact same pet from the uploaded reference photo',
                'preserve facial structure, breed traits, fur color, markings, eye color, ear shape and body proportions',
                'do not change the pet\'s identity or distinctive physical features',
                'full-body portrait of the pet sitting upright',
                'front-facing pose, looking calmly toward the viewer',
                'natural dignified posture with front paws resting on the ground',
                'large symmetrical white angel wings extending from behind the shoulders',
                'wings softly spread outward and upward',
                'detailed layered feathers with delicate light gray and cream shading',
                'angel wings positioned naturally behind the pet without covering the face or body',
                'soft hand-painted watercolor illustration',
                'delicate watercolor washes and subtle pigment variation',
                'light ink and brush detailing around the fur',
                'translate the pet\'s real fur into elegant watercolor brushwork',
                'soft natural edges with selective fine details around the face and eyes',
                'warm ivory watercolor-paper background',
                'minimal uncluttered composition',
                'subtle grounding shadow beneath the pet',
                'centered symmetrical memorial portrait',
                'generous negative space around the wings',
                'additional clean space beneath the pet for memorial text',
                'gentle peaceful and affectionate atmosphere',
                'tasteful premium remembrance artwork',
                'museum-quality custom pet memorial illustration',
                'vertical 4:5 print composition',
                'isolated artwork only, no frame and no interior mockup',
            ),
            'composition' => 'centered symmetrical full-body memorial portrait with angel wings and clean space beneath for optional memorial typography',
            'negative_extra' => array(
                'no watermark, no logo',
                'no extra animals',
                'no humans or human body parts',
                'no human facial features',
                'no breed changes',
                'no fur color changes',
                'no altered markings',
                'no clothing or accessories unless present in the uploaded reference image',
                'no halo unless specifically requested',
                'no more than two wings',
                'no dark or black wings',
                'no wings growing from the head',
                'no wings covering the face',
                'no asymmetrical or malformed wings',
                'no exaggerated wing size',
                'no exaggerated anatomy',
                'no oversized head',
                'no distorted face',
                'no deformed eyes',
                'no mismatched eyes',
                'no extra paws or legs',
                'no missing limbs',
                'no fused paws',
                'do not crop the ears, paws, tail or wings',
                'no photorealistic background',
                'no room, wall, floor, picture frame or product mockup',
                'no oil painting style',
                'no cartoon style',
                'no anime style',
                'no 3D render',
                'no heavy outlines',
                'no saturated or neon colors',
                'no busy decorative background',
                'no blurry face',
                'no low-quality render',
            ),
        ),
    );

    /**
     * Situation / character context
     *
     * @var array
     */
    const SITUATION_DEFINITIONS = array(
        'original' => array(
            'minimal_transform' => true,
            'lines'             => array(
                'no character costume or outfit overlay: keep the pet true to the uploaded reference photo',
                'preserve natural appearance, pose, expression, and markings while applying only the selected art style treatment',
                'minimal character transformation: stay as faithful as possible to the original photo',
            ),
        ),
        'neutral' => array(
            'minimal_transform' => true,
            'lines'             => array(
                'minimal transformation: keep the original pose, framing, and composition as close as the style allows',
                'treat the style as a surface treatment over the existing layout, not a full scene rewrite',
            ),
        ),
        'royal' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: regal royal portrait as king or queen with crown or tiara and elegant ceremonial attire',
                'keep the chosen art style rendering while adding royal character styling',
                'palace or velvet drapery hints, dignified classical portrait composition',
            ),
        ),
        'cowboy' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: western cowboy with hat, bandana, denim or leather accents',
                'keep the chosen art style rendering while adding cowboy character styling',
                'rugged frontier scenery, golden hour warmth where appropriate',
            ),
        ),
        'firefighter' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: heroic firefighter with uniform, helmet, and reflective gear',
                'keep the chosen art style rendering while adding firefighter character styling',
                'dramatic courageous setting with warm emergency lighting atmosphere',
            ),
        ),
        'astronaut' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: astronaut in space suit with helmet',
                'keep the chosen art style rendering while adding astronaut character styling',
                'cosmic background with stars, nebula, or space station hints',
            ),
        ),
        'pirate' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: classic pirate with hat, coat, and nautical adventure styling',
                'keep the chosen art style rendering while adding pirate character styling',
                'ocean, ship deck, or coastal ship atmosphere',
            ),
        ),
        'gentleman' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: sophisticated gentleman in formal tailored attire, tuxedo, bow tie or waistcoat',
                'keep the chosen art style rendering while adding gentleman character styling',
                'elegant refined portrait with luxury editorial polish',
            ),
        ),
        'knight' => array(
            'lines' => array(
                'character layer applied on top of the selected art style: medieval knight with armor, cape, or heraldic styling',
                'keep the chosen art style rendering while adding knight character styling',
                'majestic castle backdrop with stone battlements or medieval hall atmosphere',
            ),
        ),
        'magazine_cover' => array(
            'lines'                 => array(
                'character layer applied on top of the selected art style: premium editorial magazine cover hero portrait with headline-friendly layout',
                'keep the chosen art style rendering while adding magazine cover character styling',
                'clear masthead space and cover-style typography rhythm',
            ),
            'composition_priority' => 'editorial magazine cover layout with intentional headline space, layered on the selected art style',
        ),
        'commander' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: military commander with dress uniform, medals, and authoritative leadership presence',
                'keep the chosen art style rendering while adding commander theme styling',
                'command center, naval bridge, or strategic military briefing atmosphere',
            ),
            'composition_priority' => 'commander portrait in military uniform with authoritative leadership atmosphere',
        ),
        'biker' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: classic biker with leather jacket, bandana, and motorcycle culture styling',
                'keep the chosen art style rendering while adding biker theme styling',
                'open road, garage, or motorcycle club atmosphere with gritty attitude',
            ),
            'composition_priority' => 'biker portrait with leather jacket and motorcycle culture atmosphere',
        ),
        'doctor' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: professional doctor in white coat with stethoscope and caring medical presence',
                'keep the chosen art style rendering while adding doctor theme styling',
                'hospital, clinic, or medical office atmosphere with clean professional lighting',
            ),
            'composition_priority' => 'doctor portrait in white coat with medical professional atmosphere',
        ),
        'fisherman' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: seasoned fisherman with rain gear, boots, and coastal working-class charm',
                'keep the chosen art style rendering while adding fisherman theme styling',
                'harbor dock, fishing boat deck, or misty coastal waters atmosphere',
            ),
            'composition_priority' => 'fisherman portrait with coastal harbor or boat deck atmosphere',
        ),
        'basketball_player' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: basketball player in jersey, shorts, and athletic game-ready energy',
                'keep the chosen art style rendering while adding basketball player theme styling',
                'indoor basketball court, arena lights, or dynamic sports action atmosphere',
            ),
            'composition_priority' => 'basketball player portrait in jersey with court or arena atmosphere',
        ),
        'baseball_player' => array(
            'lines' => array(
                'theme layer applied on top of the selected art style: baseball player in uniform, cap, and classic Americana sports styling',
                'keep the chosen art style rendering while adding baseball player theme styling',
                'baseball diamond, dugout, or stadium field atmosphere under bright game-day lights',
            ),
            'composition_priority' => 'baseball player portrait in uniform with stadium or field atmosphere',
        ),
    );

    /**
     * Background color → prompt fragment (merged with style/situation)
     *
     * @var array
     */
    const BACKGROUND_PHRASES = array(
        'natural'    => '',
        'white'      => 'background: clean white or very light neutral studio backdrop',
        'black'      => 'background: deep black or charcoal seamless backdrop',
        'navy'       => 'background: rich navy and midnight blue tones',
        'cream'      => 'background: warm cream, ivory, and soft beige',
        'sage'       => 'background: muted sage green and soft eucalyptus',
        'gray'       => 'background: soft gray and cool neutral gradient',
        'burgundy'   => 'background: deep burgundy and wine red atmosphere',
        'gold'       => 'background: warm gold and amber glow',
        'teal'       => 'background: teal and deep turquoise wash',
    );

    /**
     * Default selections
     *
     * @var array
     */
    const DEFAULTS = array(
        'style'              => 'warhol_grid',
        'situation'          => 'original',
        'background_color'   => 'natural',
        'pet_name'           => '',
        'memorial_name'      => '',
        'memorial_dates'     => '',
        'memorial_message'   => '',
    );

    /**
     * Max length for pet name (Black Studio and similar styles).
     */
    const PET_NAME_MAX_LEN = 40;

    /**
     * Max lengths for memorial text fields.
     */
    const MEMORIAL_NAME_MAX_LEN    = 40;
    const MEMORIAL_DATES_MAX_LEN   = 32;
    const MEMORIAL_MESSAGE_MAX_LEN = 80;

    /**
     * Ordered keys for customize sub-steps (UI + summaries)
     *
     * @var array
     */
    const CUSTOMIZE_OPTION_ORDER = array( 'style', 'situation', 'background_color' );

    /**
     * Styles that ship a complete visual theme — skip the separate theme selection step.
     *
     * @var array<int, string>
     */
    const STYLES_SKIP_THEME = array(
        'magazine_dogue',
        'royal_legacy',
        'black_studio',
        'whiskey_office',
        'toilet',
        'memorial',
        'portrait',
    );

    /**
     * Styles with a fixed scene/background — skip the background color sub-step.
     *
     * @var array<int, string>
     */
    const STYLES_SKIP_BACKGROUND = array(
        'warhol_grid',
        'royal_legacy',
        'whiskey_office',
        'toilet',
        'black_studio',
    );

    /**
     * Per-style background choice restrictions (style slug => allowed choice keys).
     *
     * @var array<string, array<int, string>>
     */
    const STYLE_BACKGROUND_CHOICE_RESTRICTIONS = array(
        'portrait' => array( 'black', 'white' ),
    );

    /**
     * Subdirectory under wp-content/uploads for style card thumbnails.
     *
     * Use files named {slug}.webp (or jpg/png), e.g. warhol_grid.webp.
     */
    const STYLE_EXAMPLES_UPLOAD_SUBDIR = 'wc-aicc-style-examples';

    /**
     * Alternate filenames for style preview images (legacy or human-readable names).
     *
     * @var array<string, string[]>
     */
    private const STYLE_EXAMPLE_ALIASES = array(
        'black_studio'   => array( 'black_white', 'Black Studio', 'Studio Portrait' ),
        'magazine_dogue' => array( 'dogue', 'Dogue Cover' ),
        'royal_legacy'   => array( 'Royal Legacy' ),
        'toilet'         => array( 'Toilet' ),
        'memorial'       => array( 'Memorial' ),
    );

    /**
     * Customize step order (filterable for extra steps)
     *
     * @return array
     */
    public static function get_customize_option_order() {
        return apply_filters( 'wc_aicc_customize_option_order', self::CUSTOMIZE_OPTION_ORDER );
    }

    /**
     * Get customize flow metadata for JS
     *
     * @return array
     */
    public static function get_customize_flow_meta() {
        $cfg = self::get_options_config();
        $out = array();
        foreach ( self::get_customize_option_order() as $key ) {
            if ( ! isset( $cfg[ $key ] ) ) {
                continue;
            }
            $out[] = array(
                'key'   => $key,
                'title' => $cfg[ $key ]['step_title'] ?? '',
            );
        }
        return $out;
    }

    /**
     * Style slugs that skip the theme selection sub-step.
     *
     * @return array<int, string>
     */
    public static function get_styles_skip_theme() {
        /**
         * Styles with a built-in theme that should not show the theme picker.
         *
         * @param array<int, string> $slugs Style slugs.
         */
        return apply_filters( 'wc_aicc_styles_skip_theme', self::STYLES_SKIP_THEME );
    }

    /**
     * Whether a style skips the theme selection sub-step.
     *
     * @param string $style_key Style slug.
     * @return bool
     */
    public static function style_skips_theme( $style_key ) {
        return in_array( sanitize_key( (string) $style_key ), self::get_styles_skip_theme(), true );
    }

    /**
     * Style slugs that skip the background color sub-step.
     *
     * @return array<int, string>
     */
    public static function get_styles_skip_background() {
        /**
         * Styles with a built-in background that should not show the color picker.
         *
         * @param array<int, string> $slugs Style slugs.
         */
        return apply_filters( 'wc_aicc_styles_skip_background', self::STYLES_SKIP_BACKGROUND );
    }

    /**
     * Whether a style skips the background color sub-step.
     *
     * @param string $style_key Style slug.
     * @return bool
     */
    public static function style_skips_background( $style_key ) {
        return in_array( sanitize_key( (string) $style_key ), self::get_styles_skip_background(), true );
    }

    /**
     * Per-style background choice restrictions for the configurator UI and sanitization.
     *
     * @return array<string, array<int, string>>
     */
    public static function get_style_background_choice_restrictions() {
        /**
         * Restrict background swatches for specific styles (e.g. Portrait → black/white only).
         *
         * @param array<string, array<int, string>> $restrictions Style slug => choice keys.
         */
        return apply_filters( 'wc_aicc_style_background_choice_restrictions', self::STYLE_BACKGROUND_CHOICE_RESTRICTIONS );
    }

    /**
     * Allowed background choice keys for a style, or null when all backgrounds apply.
     *
     * @param string $style_key Style slug.
     * @return array<int, string>|null
     */
    public static function get_allowed_background_choices_for_style( $style_key ) {
        $style_key    = sanitize_key( (string) $style_key );
        $restrictions = self::get_style_background_choice_restrictions();
        if ( empty( $restrictions[ $style_key ] ) || ! is_array( $restrictions[ $style_key ] ) ) {
            return null;
        }
        return array_values( $restrictions[ $style_key ] );
    }

    /**
     * Customize flow with style only (no theme or background).
     *
     * @return array<int, array{key: string, title: string}>
     */
    private static function get_flow_style_only() {
        $cfg = self::get_options_config();
        if ( ! isset( $cfg['style'] ) ) {
            return array();
        }
        return array(
            array(
                'key'   => 'style',
                'title' => $cfg['style']['step_title'] ?? '',
            ),
        );
    }

    /**
     * Customize flow with style and theme only (no background).
     *
     * @return array<int, array{key: string, title: string}>
     */
    private static function get_flow_style_and_theme_only() {
        $out = array();
        foreach ( self::get_customize_flow_meta() as $step ) {
            if ( in_array( $step['key'], array( 'style', 'situation' ), true ) ) {
                $out[] = $step;
            }
        }
        return $out;
    }

    /**
     * Customize flow for Studio Portrait: style → optional pet name.
     *
     * @return array<int, array{key: string, title: string}>
     */
    private static function get_flow_studio_portrait() {
        $cfg = self::get_options_config();
        $out = array();

        if ( isset( $cfg['style'] ) ) {
            $out[] = array(
                'key'   => 'style',
                'title' => $cfg['style']['step_title'] ?? '',
            );
        }

        $out[] = array(
            'key'   => 'pet_name',
            'title' => __( 'Add your pet\'s name', 'wc-aicc' ),
        );

        return $out;
    }

    /**
     * Customize flow with style and background only (no theme step).
     *
     * @return array<int, array{key: string, title: string}>
     */
    private static function get_flow_style_and_background_only() {
        $out = array();
        foreach ( self::get_customize_flow_meta() as $step ) {
            if ( in_array( $step['key'], array( 'style', 'background_color' ), true ) ) {
                $out[] = $step;
            }
        }
        return $out;
    }

    /**
     * Customize flow for memorial: style → memorial text → background.
     *
     * @return array<int, array{key: string, title: string}>
     */
    private static function get_flow_memorial() {
        $cfg = self::get_options_config();
        $out = array();

        if ( isset( $cfg['style'] ) ) {
            $out[] = array(
                'key'   => 'style',
                'title' => $cfg['style']['step_title'] ?? '',
            );
        }

        $out[] = array(
            'key'   => 'memorial_text',
            'title' => __( 'Memorial details', 'wc-aicc' ),
        );

        if ( isset( $cfg['background_color'] ) ) {
            $out[] = array(
                'key'   => 'background_color',
                'title' => $cfg['background_color']['step_title'] ?? '',
            );
        }

        return $out;
    }

    /**
     * Per-style customize sub-step flows (override default style → situation → background).
     *
     * @return array<string, array<int, array{key: string, title: string}>>
     */
    public static function get_style_customize_flows() {
        $style_background_flow = self::get_flow_style_and_background_only();
        $flows                 = array(
            'warhol_grid' => self::get_flow_style_and_theme_only(),
        );

        foreach ( self::get_styles_skip_theme() as $style_key ) {
            $slug = sanitize_key( $style_key );
            if ( 'memorial' === $slug ) {
                $flows[ $slug ] = self::get_flow_memorial();
            } elseif ( 'black_studio' === $slug ) {
                $flows[ $slug ] = self::get_flow_studio_portrait();
            } elseif ( self::style_skips_background( $slug ) ) {
                $flows[ $slug ] = self::get_flow_style_only();
            } else {
                $flows[ $slug ] = $style_background_flow;
            }
        }

        /**
         * Per-style customize sub-step order (style → theme → background by default).
         *
         * @param array<string, array<int, array{key: string, title: string}>> $flows Style slug => steps.
         */
        return apply_filters( 'wc_aicc_style_customize_flows', $flows );
    }

    /**
     * Customize flow for a given style selection.
     *
     * @param string $style_key Style slug.
     * @return array<int, array{key: string, title: string}>
     */
    public static function get_customize_flow_for_style( $style_key ) {
        $style_key = sanitize_key( (string) $style_key );
        $flows     = self::get_style_customize_flows();
        if ( isset( $flows[ $style_key ] ) && is_array( $flows[ $style_key ] ) ) {
            return $flows[ $style_key ];
        }
        return self::get_customize_flow_meta();
    }

    /**
     * Options for UI (extensible via filter)
     *
     * @return array
     */
    public static function get_options_config() {
        $config = array(
            'style' => array(
                'label'      => __( 'Style', 'wc-aicc' ),
                'step'       => 1,
                'step_title' => __( 'Select style', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'warhol_grid' => array(
                        'label' => __( 'Warhol Grid', 'wc-aicc' ),
                        'hint'  => __( 'Four-panel Andy Warhol style', 'wc-aicc' ),
                    ),
                    'watercolor' => array(
                        'label' => __( 'Water Color', 'wc-aicc' ),
                        'hint'  => __( 'Soft washes on paper', 'wc-aicc' ),
                    ),
                    'pop_art' => array(
                        'label' => __( 'Pop Art', 'wc-aicc' ),
                        'hint'  => __( 'Bold graphic color blocks', 'wc-aicc' ),
                    ),
                    'pixar' => array(
                        'label' => __( 'Pixar', 'wc-aicc' ),
                        'hint'  => __( '3D animated film look', 'wc-aicc' ),
                    ),
                    'impasto' => array(
                        'label' => __( 'Impasto', 'wc-aicc' ),
                        'hint'  => __( 'Thick textured oil paint', 'wc-aicc' ),
                    ),
                    'american_traditional_tattoo' => array(
                        'label' => __( 'American Tradi', 'wc-aicc' ),
                        'hint'  => __( 'Traditional tattoo flash', 'wc-aicc' ),
                    ),
                    'magazine_dogue' => array(
                        'label' => __( 'Dogue Cover', 'wc-aicc' ),
                        'hint'  => __( 'Luxury fashion magazine illustration', 'wc-aicc' ),
                    ),
                    'royal_legacy' => array(
                        'label' => __( 'Royal Legacy', 'wc-aicc' ),
                        'hint'  => __( 'Regal old-master portrait', 'wc-aicc' ),
                    ),
                    'black_studio' => array(
                        'label' => __( 'Studio Portrait', 'wc-aicc' ),
                        'hint'  => __( 'Minimal charcoal portrait with optional pet name', 'wc-aicc' ),
                    ),
                    'whiskey_office' => array(
                        'label' => __( 'Whiskey Office', 'wc-aicc' ),
                        'hint'  => __( 'Mob boss executive portrait', 'wc-aicc' ),
                    ),
                    'toilet' => array(
                        'label'         => __( 'Toilet', 'wc-aicc' ),
                        'hint'          => __( 'Humorous bathroom portrait with newspaper', 'wc-aicc' ),
                        'example_image' => ( defined( 'WC_AICC_PLUGIN_URL' ) ? WC_AICC_PLUGIN_URL . 'assets/images/style-examples/toilet.webp' : '' ),
                    ),
                    'memorial' => array(
                        'label'         => __( 'Memorial', 'wc-aicc' ),
                        'hint'          => __( 'Watercolor angel wings with optional memorial text', 'wc-aicc' ),
                        'example_image' => ( defined( 'WC_AICC_PLUGIN_URL' ) ? WC_AICC_PLUGIN_URL . 'assets/images/style-examples/memorial.webp' : '' ),
                    ),
                ),
            ),
            'situation' => array(
                'label'      => __( 'Theme', 'wc-aicc' ),
                'step'       => 2,
                'step_title' => __( 'Select theme', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'original' => array(
                        'icon'  => "\u{1F43E}",
                        'label' => __( 'Original', 'wc-aicc' ),
                        'hint'  => __( 'True to your original photo', 'wc-aicc' ),
                    ),
                    'royal' => array(
                        'icon'  => "\u{1F451}",
                        'label' => __( 'Royal', 'wc-aicc' ),
                        'hint'  => __( 'King or queen portrait with elegant attire', 'wc-aicc' ),
                    ),
                    'cowboy' => array(
                        'icon'  => "\u{1F920}",
                        'label' => __( 'Cowboy', 'wc-aicc' ),
                        'hint'  => __( 'Western outfit with rugged scenery', 'wc-aicc' ),
                    ),
                    'firefighter' => array(
                        'icon'  => "\u{1F692}",
                        'label' => __( 'Firefighter', 'wc-aicc' ),
                        'hint'  => __( 'Heroic firefighter uniform and setting', 'wc-aicc' ),
                    ),
                    'astronaut' => array(
                        'icon'  => "\u{1F468}\u{200D}\u{1F680}",
                        'label' => __( 'Astronaut', 'wc-aicc' ),
                        'hint'  => __( 'Space suit with a cosmic background', 'wc-aicc' ),
                    ),
                    'pirate' => array(
                        'icon'  => "\u{1F3F4}\u{200D}\u{2620}\u{FE0F}",
                        'label' => __( 'Pirate', 'wc-aicc' ),
                        'hint'  => __( 'Classic pirate outfit and ship atmosphere', 'wc-aicc' ),
                    ),
                    'gentleman' => array(
                        'icon'  => "\u{1F3A9}",
                        'label' => __( 'Gentleman', 'wc-aicc' ),
                        'hint'  => __( 'Sophisticated formal portrait in a tuxedo', 'wc-aicc' ),
                    ),
                    'knight' => array(
                        'icon'  => "\u{2694}\u{FE0F}",
                        'label' => __( 'Knight', 'wc-aicc' ),
                        'hint'  => __( 'Medieval armor with a majestic castle backdrop', 'wc-aicc' ),
                    ),
                    'magazine_cover' => array(
                        'icon'  => "\u{1F4D6}",
                        'label' => __( 'Magazine Cover', 'wc-aicc' ),
                        'hint'  => __( 'Premium editorial cover with headlines', 'wc-aicc' ),
                    ),
                    'commander' => array(
                        'icon'  => "\u{1F396}\u{FE0F}",
                        'label' => __( 'Commander', 'wc-aicc' ),
                        'hint'  => __( 'Military uniform with leadership presence', 'wc-aicc' ),
                    ),
                    'biker' => array(
                        'icon'  => "\u{1F3CD}\u{FE0F}",
                        'label' => __( 'Biker', 'wc-aicc' ),
                        'hint'  => __( 'Leather jacket and motorcycle culture', 'wc-aicc' ),
                    ),
                    'doctor' => array(
                        'icon'  => "\u{2695}\u{FE0F}",
                        'label' => __( 'Doctor', 'wc-aicc' ),
                        'hint'  => __( 'White coat with a medical setting', 'wc-aicc' ),
                    ),
                    'fisherman' => array(
                        'icon'  => "\u{1F3A3}",
                        'label' => __( 'Fisherman', 'wc-aicc' ),
                        'hint'  => __( 'Coastal gear with harbor or boat atmosphere', 'wc-aicc' ),
                    ),
                    'basketball_player' => array(
                        'icon'  => "\u{1F3C0}",
                        'label' => __( 'Basketball Player', 'wc-aicc' ),
                        'hint'  => __( 'Jersey and court or arena energy', 'wc-aicc' ),
                    ),
                    'baseball_player' => array(
                        'icon'  => "\u{26BE}\u{FE0F}",
                        'label' => __( 'Baseball Player', 'wc-aicc' ),
                        'hint'  => __( 'Uniform and stadium field atmosphere', 'wc-aicc' ),
                    ),
                ),
            ),
            'background_color' => array(
                'label'      => __( 'Background color', 'wc-aicc' ),
                'step'       => 3,
                'step_title' => __( 'Select background color', 'wc-aicc' ),
                'type'       => 'cards',
                'choices'    => array(
                    'natural' => array(
                        'label'  => __( 'Natural to style', 'wc-aicc' ),
                        'hint'   => __( 'Let the AI choose', 'wc-aicc' ),
                        'swatch' => 'auto',
                    ),
                    'white' => array(
                        'label'  => __( 'White', 'wc-aicc' ),
                        'hint'   => __( 'Clean studio light', 'wc-aicc' ),
                        'swatch' => '#f8f8f8',
                    ),
                    'black' => array(
                        'label'  => __( 'Black', 'wc-aicc' ),
                        'hint'   => __( 'Deep dramatic backdrop', 'wc-aicc' ),
                        'swatch' => '#1a1a1a',
                    ),
                    'navy' => array(
                        'label'  => __( 'Navy', 'wc-aicc' ),
                        'hint'   => __( 'Midnight blues', 'wc-aicc' ),
                        'swatch' => '#1e3a5f',
                    ),
                    'cream' => array(
                        'label'  => __( 'Cream', 'wc-aicc' ),
                        'hint'   => __( 'Warm ivory tones', 'wc-aicc' ),
                        'swatch' => '#f5f0e6',
                    ),
                    'sage' => array(
                        'label'  => __( 'Sage', 'wc-aicc' ),
                        'hint'   => __( 'Muted green', 'wc-aicc' ),
                        'swatch' => '#9caf88',
                    ),
                    'gray' => array(
                        'label'  => __( 'Gray', 'wc-aicc' ),
                        'hint'   => __( 'Cool neutral', 'wc-aicc' ),
                        'swatch' => '#b0b0b0',
                    ),
                    'burgundy' => array(
                        'label'  => __( 'Burgundy', 'wc-aicc' ),
                        'hint'   => __( 'Rich wine red', 'wc-aicc' ),
                        'swatch' => '#6b2d3e',
                    ),
                    'gold' => array(
                        'label'  => __( 'Gold', 'wc-aicc' ),
                        'hint'   => __( 'Warm amber glow', 'wc-aicc' ),
                        'swatch' => '#c9a227',
                    ),
                    'teal' => array(
                        'label'  => __( 'Teal', 'wc-aicc' ),
                        'hint'   => __( 'Turquoise depth', 'wc-aicc' ),
                        'swatch' => '#0d6e6e',
                    ),
                ),
            ),
        );

        /**
         * Filter full customization config (add choices, steps, or labels).
         *
         * @param array $config Options config.
         */
        return apply_filters( 'wc_aicc_prompt_builder_options_config', $config );
    }

    /**
     * Public URL for a style preview image (step 3.1 cards).
     *
     * Resolution order:
     * 1. wp-content/uploads/wc-aicc-style-examples/{slug}.(webp|jpg|jpeg|png)
     * 2. Plugin bundle: assets/images/style-examples/{slug}.(webp|jpg|jpeg|png)
     * 3. Known aliases and normalized filename scan in those directories
     *
     * @param string $style_slug Style choice key (e.g. warhol_grid, watercolor).
     * @return string URL or empty if no file exists.
     */
    public static function resolve_style_example_image_url( $style_slug ) {
        $slug = preg_replace( '/[^a-z0-9_-]/i', '', (string) $style_slug );
        if ( $slug === '' || ! defined( 'WC_AICC_PLUGIN_DIR' ) || ! defined( 'WC_AICC_PLUGIN_URL' ) ) {
            return '';
        }

        $extensions  = array( 'webp', 'jpg', 'jpeg', 'png' );
        $candidates  = self::style_example_file_candidates( $slug );

        if ( function_exists( 'wp_upload_dir' ) ) {
            $upload = wp_upload_dir();
            if ( empty( $upload['error'] ) && ! empty( $upload['basedir'] ) && ! empty( $upload['baseurl'] ) ) {
                $upload_style_dir = trailingslashit( $upload['basedir'] ) . self::STYLE_EXAMPLES_UPLOAD_SUBDIR;
                if ( function_exists( 'wp_mkdir_p' ) && ! is_dir( $upload_style_dir ) ) {
                    wp_mkdir_p( $upload_style_dir );
                }
                if ( is_dir( $upload_style_dir ) && ! file_exists( $upload_style_dir . '/index.php' ) ) {
                    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
                    @file_put_contents( $upload_style_dir . '/index.php', "<?php\n// Silence is golden.\n" );
                }
                $found = self::find_style_example_asset( $upload_style_dir, $candidates, $extensions );
                if ( $found ) {
                    $upload_style_url = trailingslashit( $upload['baseurl'] ) . self::STYLE_EXAMPLES_UPLOAD_SUBDIR;
                    $url              = self::style_example_url_from_file( $upload_style_url, $found );
                    return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $found );
                }
            }
        }

        $subdir = 'assets/images/style-examples/';
        $dir    = WC_AICC_PLUGIN_DIR . $subdir;
        $base   = WC_AICC_PLUGIN_URL . $subdir;

        $found = self::find_style_example_asset( $dir, $candidates, $extensions );
        if ( $found ) {
            $url = self::style_example_url_from_file( $base, $found );
            return (string) apply_filters( 'wc_aicc_style_example_image_url', $url, $slug, $found );
        }

        return (string) apply_filters( 'wc_aicc_style_example_image_url', '', $slug, '' );
    }

    /**
     * Style slugs that have no bundled/upload preview image.
     *
     * @return string[]
     */
    public static function get_style_slugs_missing_example_images() {
        $missing = array();
        foreach ( self::get_choice_keys( 'style' ) as $slug ) {
            if ( self::resolve_style_example_image_url( $slug ) === '' ) {
                $missing[] = $slug;
            }
        }
        return $missing;
    }

    /**
     * @param string $slug Style slug.
     * @return string[]
     */
    private static function style_example_file_candidates( $slug ) {
        $candidates = array( $slug );
        if ( isset( self::STYLE_EXAMPLE_ALIASES[ $slug ] ) ) {
            $candidates = array_merge( $candidates, self::STYLE_EXAMPLE_ALIASES[ $slug ] );
        }
        return array_values( array_unique( $candidates ) );
    }

    /**
     * @param string $name Basename or label.
     * @return string
     */
    private static function normalize_style_example_key( $name ) {
        $name = strtolower( (string) $name );
        $name = preg_replace( '/[\s_-]+/', '_', $name );
        return (string) preg_replace( '/[^a-z0-9_]/', '', $name );
    }

    /**
     * @param string   $dir         Directory to search.
     * @param string[] $candidates  Basename candidates (without extension).
     * @param string[] $extensions  Allowed extensions.
     * @return string Absolute file path or empty string.
     */
    private static function find_style_example_asset( $dir, $candidates, $extensions ) {
        if ( ! is_dir( $dir ) ) {
            return '';
        }

        foreach ( $candidates as $base ) {
            foreach ( $extensions as $ext ) {
                $file = trailingslashit( $dir ) . $base . '.' . $ext;
                if ( file_exists( $file ) && is_readable( $file ) ) {
                    return $file;
                }
            }
        }

        $normalized_want = array();
        foreach ( $candidates as $base ) {
            $norm = self::normalize_style_example_key( $base );
            if ( $norm !== '' ) {
                $normalized_want[ $norm ] = true;
            }
        }
        if ( empty( $normalized_want ) ) {
            return '';
        }

        $entries = scandir( $dir );
        if ( ! is_array( $entries ) ) {
            return '';
        }

        foreach ( $entries as $entry ) {
            if ( $entry === '.' || $entry === '..' || $entry === 'index.php' ) {
                continue;
            }
            $path = trailingslashit( $dir ) . $entry;
            if ( ! is_file( $path ) || ! is_readable( $path ) ) {
                continue;
            }
            $info = pathinfo( $entry );
            $ext  = strtolower( (string) ( $info['extension'] ?? '' ) );
            if ( ! in_array( $ext, $extensions, true ) ) {
                continue;
            }
            $norm = self::normalize_style_example_key( (string) ( $info['filename'] ?? '' ) );
            if ( $norm !== '' && isset( $normalized_want[ $norm ] ) ) {
                return $path;
            }
        }

        return '';
    }

    /**
     * @param string $url_base Public URL base (with trailing slash).
     * @param string $file     Absolute filesystem path.
     * @return string
     */
    private static function style_example_url_from_file( $url_base, $file ) {
        $filename = basename( $file );
        $url      = trailingslashit( $url_base ) . $filename;
        $mtime    = @filemtime( $file );
        if ( $mtime ) {
            $url .= '?v=' . (int) $mtime;
        }
        return $url;
    }

    /**
     * Valid choice keys for an option (for sanitization)
     *
     * @param string $option_key Option key.
     * @return array
     */
    public static function get_choice_keys( $option_key ) {
        $cfg = self::get_options_config();
        if ( ! isset( $cfg[ $option_key ]['choices'] ) ) {
            return array();
        }
        return array_keys( $cfg[ $option_key ]['choices'] );
    }

    /**
     * Human-readable label for a choice (cart, orders, admin)
     *
     * @param string $option_key Option key.
     * @param string $value      Stored value.
     * @return string
     */
    public static function get_choice_label( $option_key, $value ) {
        if ( $value === '' || $value === null ) {
            return '';
        }
        $cfg    = self::get_options_config();
        $choice = $cfg[ $option_key ]['choices'][ $value ] ?? null;
        if ( is_array( $choice ) && isset( $choice['label'] ) ) {
            return $choice['label'];
        }
        if ( is_string( $choice ) ) {
            return $choice;
        }
        return (string) $value;
    }

    /**
     * Ordered summary labels for stored options
     *
     * @param array $options Sanitized options.
     * @return string[]
     */
    public static function summarize_option_labels( array $options ) {
        $parts      = array();
        $style_key  = isset( $options['style'] ) ? sanitize_key( (string) $options['style'] ) : '';
        $flow_steps = self::get_customize_flow_for_style( $style_key );

        foreach ( $flow_steps as $step ) {
            $key = $step['key'] ?? '';
            if ( $key === '' || 'pet_name' === $key ) {
                continue;
            }
            $value = $options[ $key ] ?? '';
            if ( $value === '' || $value === null ) {
                continue;
            }
            if ( 'background_color' === $key && 'natural' === $value ) {
                continue;
            }
            $label = self::get_choice_label( $key, $value );
            if ( $label !== '' ) {
                $parts[] = $label;
            }
        }

        $pet_name = isset( $options['pet_name'] ) ? trim( (string) $options['pet_name'] ) : '';
        if ( $pet_name !== '' ) {
            $parts[] = __( 'Pet name', 'wc-aicc' ) . ': ' . $pet_name;
        }

        if ( 'memorial' === $style_key ) {
            $memorial_labels = array(
                'memorial_name'    => __( 'Name', 'wc-aicc' ),
                'memorial_dates'   => __( 'Dates', 'wc-aicc' ),
                'memorial_message' => __( 'Message', 'wc-aicc' ),
            );
            foreach ( $memorial_labels as $key => $label ) {
                $value = isset( $options[ $key ] ) ? trim( (string) $options[ $key ] ) : '';
                if ( $value !== '' ) {
                    $parts[] = $label . ': ' . $value;
                }
            }
        }

        return $parts;
    }

    /**
     * Build prompt arrays from user customization options
     *
     * @param array $options User selections.
     * @param array $context Optional context (e.g. aspect_ratio from build).
     * @return array { prompt: string, negative_prompt: string }
     */
    public static function build( $options = array(), $context = array() ) {
        $options = self::sanitize_options( is_array( $options ) ? $options : array() );
        $context = is_array( $context ) ? $context : array();

        $style_key     = $options['style'];
        $situation_key = $options['situation'];
        $bg_key        = $options['background_color'];

        $styles     = apply_filters( 'wc_aicc_prompt_style_definitions', self::STYLE_DEFINITIONS );
        $situations = apply_filters( 'wc_aicc_prompt_situation_definitions', self::SITUATION_DEFINITIONS );
        $bg_map     = apply_filters( 'wc_aicc_prompt_background_phrases', self::BACKGROUND_PHRASES );

        $style_def = $styles[ $style_key ] ?? $styles['warhol_grid'];
        $sit_def   = $situations[ $situation_key ] ?? $situations['original'];

        $skip_bg = ! empty( $style_def['skip_background_option'] ) || self::style_skips_background( $style_key );

        $prompt_parts = array();

        $aspect_ratio = isset( $context['aspect_ratio'] ) ? self::sanitize_aspect_ratio( $context['aspect_ratio'] ) : '';
        if ( $aspect_ratio !== '' ) {
            $prompt_parts[] = 'output aspect ratio ' . $aspect_ratio . ', portrait vertical canvas, compose the full artwork within frame bounds including all typography';
        }

        // Reference + identity (some styles ship their own likeness lines).
        if ( empty( $style_def['skip_identity_lines'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, self::IDENTITY_LINES );
        }

        // Style core
        if ( ! empty( $style_def['core'] ) && is_array( $style_def['core'] ) ) {
            $prompt_parts = array_merge( $prompt_parts, $style_def['core'] );
        }

        // Optional pet name typography (Studio Portrait).
        if ( 'black_studio' === $style_key ) {
            $pet_name = self::sanitize_pet_name( $options['pet_name'] ?? '' );
            if ( $pet_name !== '' ) {
                $prompt_parts[] = 'Pet name: "' . $pet_name . '" centered above the pet, modern minimalist sans-serif font, all uppercase, white text, wide letter spacing, small size relative to canvas, luxury editorial aesthetic';
            }
        }

        // Memorial typography (optional name, dates, message).
        if ( 'memorial' === $style_key ) {
            $prompt_parts = array_merge( $prompt_parts, self::build_memorial_text_lines( $options ) );
        }

        // Theme context layered on top of the selected art style (skipped for built-in-theme styles).
        if ( ! self::style_skips_theme( $style_key ) ) {
            if ( ! empty( $sit_def['lines'] ) && is_array( $sit_def['lines'] ) ) {
                $prompt_parts = array_merge( $prompt_parts, $sit_def['lines'] );
            }

            // Composition: theme override wins when set; otherwise use style framing.
            if ( ! empty( $sit_def['composition_priority'] ) ) {
                $prompt_parts[] = 'composition priority: ' . $sit_def['composition_priority'];
            } elseif ( empty( $sit_def['minimal_transform'] ) && ! empty( $style_def['composition'] ) ) {
                $prompt_parts[] = $style_def['composition'];
            }
        }

        // Background color (skip for fixed-background styles).
        if ( ! $skip_bg ) {
            $bg_phrase = $bg_map[ $bg_key ] ?? '';
            if ( $bg_phrase !== '' ) {
                $prompt_parts[] = $bg_phrase;
            }
        }

        /**
         * Final prompt fragments before join (expert tuning).
         *
         * @param array  $prompt_parts Prompt fragments.
         * @param array  $options      Sanitized options.
         */
        $prompt_parts = apply_filters( 'wc_aicc_prompt_fragments', $prompt_parts, $options );

        $prompt = implode( ', ', array_filter( array_map( 'trim', $prompt_parts ) ) );

        $negative = self::CONSTRAINTS;
        $allows_text_in_output = ! empty( $style_def['requires_pet_name'] )
            || ! empty( $style_def['allows_cover_text'] )
            || ( 'memorial' === $style_key && self::has_memorial_text( $options ) )
            || ( 'black_studio' === $style_key && self::sanitize_pet_name( $options['pet_name'] ?? '' ) !== '' );
        if ( $allows_text_in_output ) {
            $negative = array_values(
                array_filter(
                    $negative,
                    static function ( $line ) {
                        return stripos( (string) $line, 'no text' ) === false;
                    }
                )
            );
        }
        if ( ! empty( $style_def['negative_extra'] ) && is_array( $style_def['negative_extra'] ) ) {
            $negative = array_merge( $negative, $style_def['negative_extra'] );
        }

        /**
         * Negative prompt fragments.
         *
         * @param array $negative Negative lines.
         * @param array $options  Sanitized options.
         */
        $negative = apply_filters( 'wc_aicc_negative_prompt_fragments', $negative, $options );

        $negative_prompt = implode( ', ', array_filter( array_map( 'trim', $negative ) ) );

        return array(
            'prompt'            => $prompt,
            'negative_prompt'   => $negative_prompt,
        );
    }

    /**
     * Validate and sanitize options from request
     *
     * @param array $raw Raw options from user input.
     * @return array Sanitized options.
     */
    public static function sanitize_options( $raw ) {
        $raw = is_array( $raw ) ? $raw : array();

        if ( isset( $raw['style'] ) && 'black_white' === $raw['style'] ) {
            $raw['style'] = 'black_studio';
        }
        if ( isset( $raw['situation'] ) && 'neutral' === $raw['situation'] ) {
            $raw['situation'] = 'original';
        }

        $config = self::get_options_config();

        $style_value = isset( $raw['style'] ) ? sanitize_text_field( $raw['style'] ) : '';
        $style_keys  = self::get_choice_keys( 'style' );
        $style_key   = in_array( $style_value, $style_keys, true ) ? $style_value : ( self::DEFAULTS['style'] ?? '' );

        $skip_theme  = self::style_skips_theme( $style_key );
        $allowed_bg  = self::get_allowed_background_choices_for_style( $style_key );
        $result      = array();

        foreach ( array_keys( $config ) as $key ) {
            if ( $skip_theme && 'situation' === $key ) {
                continue;
            }

            $value   = isset( $raw[ $key ] ) ? sanitize_text_field( $raw[ $key ] ) : '';
            $choices = self::get_choice_keys( $key );
            if ( in_array( $value, $choices, true ) ) {
                $result[ $key ] = $value;
            } else {
                $result[ $key ] = self::DEFAULTS[ $key ] ?? '';
            }
        }

        $result['pet_name'] = self::sanitize_pet_name( $raw['pet_name'] ?? '' );
        $result['memorial_name']    = self::sanitize_memorial_text( $raw['memorial_name'] ?? '', self::MEMORIAL_NAME_MAX_LEN );
        $result['memorial_dates']   = self::sanitize_memorial_text( $raw['memorial_dates'] ?? '', self::MEMORIAL_DATES_MAX_LEN );
        $result['memorial_message'] = self::sanitize_memorial_text( $raw['memorial_message'] ?? '', self::MEMORIAL_MESSAGE_MAX_LEN );

        if ( self::style_skips_background( $style_key ) ) {
            unset( $result['background_color'] );
        }

        if ( is_array( $allowed_bg ) && ! empty( $allowed_bg ) ) {
            if ( empty( $result['background_color'] ) || ! in_array( $result['background_color'], $allowed_bg, true ) ) {
                $result['background_color'] = $allowed_bg[0];
            }
        }

        $defaults = self::DEFAULTS;
        if ( $skip_theme ) {
            unset( $defaults['situation'] );
        }
        if ( self::style_skips_background( $style_key ) ) {
            unset( $defaults['background_color'] );
        }

        $sanitized = wp_parse_args( $result, $defaults );
        if ( self::style_skips_background( $style_key ) ) {
            unset( $sanitized['background_color'] );
        }

        return $sanitized;
    }

    /**
     * Sanitize canvas aspect ratio for prompt injection.
     *
     * @param mixed $raw Raw value.
     * @return string e.g. "4:5" or empty.
     */
    public static function sanitize_aspect_ratio( $raw ) {
        $t = strtolower( preg_replace( '/\s+/', '', (string) $raw ) );
        if ( preg_match( '/^\d+:\d+$/', $t ) ) {
            return $t;
        }
        return '';
    }

    /**
     * Sanitize pet name for poster typography.
     *
     * @param mixed $raw Raw value.
     * @return string Uppercase ASCII-safe name for prompt injection.
     */
    public static function sanitize_pet_name( $raw ) {
        $t = sanitize_text_field( is_string( $raw ) ? $raw : '' );
        $t = wp_strip_all_tags( $t );
        $t = preg_replace( '/[^\p{L}\p{N}\s\'\-]/u', '', $t );
        $t = preg_replace( '/\s+/u', ' ', $t );
        $t = trim( $t );
        $max = (int) self::PET_NAME_MAX_LEN;
        if ( $max < 1 ) {
            return '';
        }
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > $max ) {
            $t = mb_substr( $t, 0, $max );
        } elseif ( strlen( $t ) > $max ) {
            $t = substr( $t, 0, $max );
        }
        if ( function_exists( 'mb_strtoupper' ) ) {
            return mb_strtoupper( $t, 'UTF-8' );
        }
        return strtoupper( $t );
    }

    /**
     * Whether memorial options include any custom text.
     *
     * @param array $options Sanitized options.
     * @return bool
     */
    public static function has_memorial_text( array $options ) {
        foreach ( array( 'memorial_name', 'memorial_dates', 'memorial_message' ) as $key ) {
            if ( ! empty( $options[ $key ] ) ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Build memorial typography prompt lines from optional user fields.
     *
     * @param array $options Sanitized options.
     * @return array<int, string>
     */
    public static function build_memorial_text_lines( array $options ) {
        $name    = self::sanitize_memorial_text( $options['memorial_name'] ?? '', self::MEMORIAL_NAME_MAX_LEN );
        $dates   = self::sanitize_memorial_text( $options['memorial_dates'] ?? '', self::MEMORIAL_DATES_MAX_LEN );
        $message = self::sanitize_memorial_text( $options['memorial_message'] ?? '', self::MEMORIAL_MESSAGE_MAX_LEN );
        $lines   = array();

        if ( $name !== '' ) {
            $lines[] = 'place the pet name centered beneath the portrait: "' . $name . '"';
            $lines[] = 'render the pet name in an elegant flowing calligraphy or refined script typeface';
        }
        if ( $dates !== '' ) {
            $lines[] = 'place the dates centered beneath the name: "' . $dates . '"';
        }
        if ( $message !== '' ) {
            $lines[] = 'place the memorial message centered beneath the dates: "' . $message . '"';
            $lines[] = 'render the memorial message in a smaller refined italic serif typeface';
        }
        if ( ! empty( $lines ) ) {
            $lines[] = 'use soft charcoal-gray typography';
            $lines[] = 'keep the text perfectly centered, legible and correctly spelled';
        }

        return $lines;
    }

    /**
     * Sanitize memorial text field for prompt injection.
     *
     * @param mixed $raw Raw value.
     * @param int   $max Max length.
     * @return string
     */
    public static function sanitize_memorial_text( $raw, $max = 80 ) {
        $t = sanitize_text_field( is_string( $raw ) ? $raw : '' );
        $t = wp_strip_all_tags( $t );
        $t = preg_replace( '/\s+/u', ' ', $t );
        $t = trim( $t );
        $max = max( 1, (int) $max );
        if ( function_exists( 'mb_strlen' ) && mb_strlen( $t ) > $max ) {
            $t = mb_substr( $t, 0, $max );
        } elseif ( strlen( $t ) > $max ) {
            $t = substr( $t, 0, $max );
        }
        return $t;
    }
}
