"""
The English half of the site's content. Same shape as data.py, same slugs and
the same image paths — only the words differ, so the templates never have to
know which language they are rendering.
"""

PROFILE = {
    "name": "Uğur Polat",
    "role": "Computer Engineer & Full-Stack Developer",
    "tagline": "From Java to Django, from Unity to framework-free PHP — in every project I chose to build it from the ground up.",
    "age": 23,
    "location": "İstanbul, Bahçelievler",
    "email": "ugurplt8@gmail.com",
    "status": "Currently studying at École 42 İstanbul",
    "bio": [
        "I am a Computer Engineering graduate of Bolu Abant İzzet Baysal University. I am now in İstanbul at École 42, a school with no grades and no teachers, where peer evaluation takes their place and the entire curriculum is projects.",
        "From a Java tower defense game written for the desktop, to two different Unity games, to a PHP management platform built from scratch without a framework, to the Django site you are reading right now — I would rather understand the foundations first and build on top of them. The four projects below are all products of that approach.",
        "I genuinely enjoy learning something new, which is exactly why École 42's peer-to-peer structure suits me: it forces both learning and working inside a team. I have experience in software development and in other sectors as well. I live in Bahçelievler, İstanbul.",
    ],
    "education": [
        {
            "school": "École 42 İstanbul",
            "degree": "Software Engineering — Project-Based Education",
            "period": "Ongoing",
            "note": "No grades, no teachers: an entirely peer-to-peer, project-driven curriculum.",
        },
        {
            "school": "Bolu Abant İzzet Baysal University",
            "degree": "Computer Engineering",
            "period": "Graduated",
            "note": "Several software projects, including a Java tower defense game written from scratch in my final year.",
        },
    ],
    "skills": [
        {
            "group": "Programming Languages",
            "featured": True,
            "items": [
                {"name": "C", "level": "Advanced", "bars": 5},
                {"name": "Java", "level": "Advanced", "bars": 5},
                {"name": "PHP", "level": "Advanced", "bars": 5},
                {"name": "Python", "level": "Intermediate", "bars": 3},
            ],
        },
        {
            "group": "Web Development",
            "items": [
                {"name": "HTML", "level": "Advanced"},
                {"name": "CSS", "level": "Advanced"},
                {"name": "JavaScript", "level": "Advanced"},
                {"name": "Django", "level": "This very site"},
            ],
        },
        {
            "group": "Game Development",
            "items": [
                {"name": "Unity", "level": "Advanced"},
                {"name": "Unreal Engine", "level": "Intermediate"},
            ],
        },
        {
            "group": "Tools",
            "items": [
                {"name": "Git", "level": ""},
                {"name": "MySQL", "level": ""},
                {"name": "Composer", "level": ""},
                {"name": "Eclipse / Visual Studio", "level": ""},
            ],
        },
    ],
    "highlights": [
        "4 end-to-end projects: 2 desktop/Unity games, 1 framework-free PHP platform, 1 Django site",
        "A tower defense game written in Java and Swing with no game engine underneath it",
        "A 20-table certification platform built on raw PDO, without Laravel or Symfony",
        "A Unity game built on NavMesh-based AI and 90+ custom Editor tools",
    ],
    "soft_skills": [
        "I enjoy learning", "I work well in a team",
        "Curious and research-driven", "I like turning nothing into a system",
    ],
}

PROJECTS = [
    {
        "slug": "oyunum",
        "name": "Tower Defense (Java)",
        "kind": "Desktop Game",
        "tagline": "A tower defense game written from scratch in Java, with no external game engine involved.",
        "accent": "#f2b84c",
        "year": "University Project",
        "cover": "portfolio/img/oyunum/combat.jpg",
        "summary": (
            "A tower defense game I wrote end to end during my computer engineering degree at Bolu Abant "
            "İzzet Baysal University, using nothing but Java and Swing — no Unity, no Godot, no ready-made "
            "engine of any kind. From the sprite engine that draws the map to the AI that runs the enemy "
            "waves, every line came out of my own hands."
        ),
        "stats": [
            {"value": "3", "label": "Tower Types"},
            {"value": "2", "label": "Enemy Types"},
            {"value": "9+", "label": "Waves"},
            {"value": "100%", "label": "Engine-Free"},
        ],
        "stack": [
            "Java", "Swing (JFrame / JPanel)", "AWT — Graphics2D", "BufferedImage & Sprite Slicing",
            "Object-Oriented Design", "Inheritance & Polymorphism", "Multithreading",
            "Event-Driven Input Handling", "Save/Load via File I/O",
        ],
        "sections": [
            {
                "heading": "A scene-based state machine",
                "body": (
                    "I built a state machine that moves between the Menu, Playing, Game Over and Editing "
                    "(map editor) scenes through a shared scene interface. Each scene owns its update and "
                    "draw loop, while the flow between them is handled from a single place."
                ),
            },
            {
                "heading": "Two threads, one consistent rhythm",
                "body": (
                    "Updating the game logic and drawing to the screen run at separate rhythms and are "
                    "synchronised to hold a steady frame rate. This was the first project where I solved a "
                    "real multithreading problem end to end inside an actual product."
                ),
            },
            {
                "heading": "Responsibilities split into manager classes",
                "body": (
                    "Every responsibility lives in its own manager class: waves, enemies, towers, projectiles "
                    "and the map. Enemy types such as the Orc and the Bat derive from a common Enemy class, "
                    "so thanks to inheritance and polymorphism, adding a new enemy means writing one subclass."
                ),
            },
            {
                "heading": "My own map editor",
                "body": (
                    "I wrote a tile-based editor: start and end points and the path itself are laid down "
                    "freely, saved to disk in my own file format, and loaded back through a save/load class. "
                    "The map in the screenshots was designed with that editor."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/oyunum/combat.jpg", "alt": "Wave 9/9 — archer and cannon towers against orcs and bats"},
            {"src": "portfolio/img/oyunum/editor.png", "alt": "My own tile-based map editor"},
        ],
        "highlights": [
            "Scene-based state machine", "Multithreading", "Custom map editor",
            "Inheritance-based enemy system", "Save/load to file", "Sprite-based animation",
        ],
        "closing": (
            "Building a complex system by cutting it into independent, testable parts — that is the most "
            "lasting skill this project gave me."
        ),
    },
    {
        "slug": "kitchen-rush",
        "name": "Kitchen Rush",
        "kind": "Unity Game",
        "tagline": "A solo-built kitchen simulation running entirely on real-time pressure.",
        "accent": "#ff7a45",
        "year": "Unity Solo Project",
        "cover": "portfolio/img/kitchenrush/kitchen-1.jpg",
        "summary": (
            "An Overcooked-inspired single-player kitchen management game. The player runs between stations, "
            "cuts ingredients, cooks them and races to get orders onto the counter before they expire. I "
            "developed it in Unity from beginning to end on my own."
        ),
        "originality": (
            "Every 3D model, texture, animation and interface element in this game was produced by hand from "
            "scratch, without a single asset pack or third-party tool."
        ),
        "stats": [
            {"value": "17", "label": "C# Scripts"},
            {"value": "6", "label": "Interactive Stations"},
            {"value": "7", "label": "NUnit Unit Tests"},
            {"value": "19", "label": "PBR-Textured Props"},
        ],
        "stack": [
            "Unity (Built-in RP)", "C#", "New Input System", "NUnit", "Coroutine-Based Timing",
            "MaterialPropertyBlock", "Mixamo Rig Pipeline", "Custom Editor Tools", "TextMeshPro",
        ],
        "sections": [
            {
                "heading": "The gameplay loop",
                "body": (
                    "I built a continuous pressure loop between order generation, preparation (cutting and "
                    "cooking), delivery and scoring: 15 seconds between orders, 3 seconds on the stove — tuned "
                    "to keep the player permanently in motion."
                ),
            },
            {
                "heading": "Architecture through IInteractable",
                "body": (
                    "Six different station types — the cutting board, the stove, the sink — all share a single "
                    "IInteractable interface. Adding a new station means writing a class that implements that "
                    "interface; the gameplay code never learns the station's concrete type."
                ),
            },
            {
                "heading": "Input, movement and animation",
                "body": (
                    "Three input actions (Move, Interact, Cut) are defined through Unity's new Input System. "
                    "The character moves with a two-state Animator (Idle/Walking) fed by a Mixamo rig pipeline."
                ),
            },
            {
                "heading": "Visual feedback without the leak",
                "body": (
                    "To build the highlight system independently of the shader I used MaterialPropertyBlock — "
                    "a light layer written to the GPU in one pass, instead of instantiating a new material "
                    "every frame and leaking memory. Timings such as cooking duration run on coroutines."
                ),
            },
            {
                "heading": "Testing and quality",
                "body": (
                    "Alongside the 16 runtime scripts I wrote 7 NUnit unit tests; this was the project where I "
                    "first took a testing culture seriously inside a Unity codebase."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/kitchenrush/kitchen-1.jpg", "alt": "Kitchen stations and prepared ingredients"},
            {"src": "portfolio/img/kitchenrush/kitchen-2.jpg", "alt": "The chef character in the middle of the kitchen"},
        ],
        "highlights": [
            "IInteractable architecture", "New Input System", "MaterialPropertyBlock",
            "Coroutine timing", "NUnit test suite", "Mixamo animation pipeline",
        ],
        "closing": (
            "Interface-first design, and the decision to automate from both the editor and the runtime, showed "
            "how much clean architecture pays off even in a small game."
        ),
    },
    {
        "slug": "rage-attack",
        "name": "Rage Attack",
        "kind": "Unity Game",
        "tagline": "One fly, one village, one complete nervous breakdown — a chaos game built on systemic AI.",
        "accent": "#b25cff",
        "year": "Unity Solo Project",
        "cover": "portfolio/img/rageattack/village-overview.jpg",
        "summary": (
            "The player takes the role of a housefly — or RobotFly, unlocked later — and descends on an entire "
            "village. Every NPC in the scene runs an independent stress simulation and escalates through a "
            "five-stage rage state machine, from mild irritation all the way to arming itself and attacking. "
            "The real engineering problem was making that simulation feel alive and out of control."
        ),
        "originality": (
            "Everything in this game, from the village buildings to the character animations, from the "
            "procedural VFX to the interface, was designed and animated by me without a single asset pack or "
            "third-party tool."
        ),
        "stats": [
            {"value": "5", "label": "Stage Rage FSM"},
            {"value": "2", "label": "Playable Characters"},
            {"value": "90+", "label": "Custom Editor Tools"},
            {"value": "0", "label": "Scripted Set Pieces"},
        ],
        "stack": [
            "Unity (URP)", "C#", "NavMesh AI", "New Input System", "TextMeshPro", "ScriptableObject",
            "Custom Editor Tools", "Coroutine State Machines", "Procedural VFX",
            "Runtime-Generated UI", "Mixamo Rig Pipeline", "PlayerPrefs",
        ],
        "sections": [
            {
                "heading": "Nerve management: a five-stage NPC rage FSM",
                "body": (
                    "Every character in the village runs its own rage state machine, climbing through five "
                    "stages from mild irritation to being triggered, to panic, and finally to arming itself "
                    "and attacking. There are no scripted set pieces — every fight, every riot, every stampede "
                    "is a side effect of these independent state machines noticing each other and the player."
                ),
            },
            {
                "heading": "Two playable characters, entirely different kits",
                "body": (
                    "The player can choose between the default agile fly and the stronger RobotFly, unlocked "
                    "through in-game kill count; the two have completely different ability sets."
                ),
            },
            {
                "heading": "XP, card-based upgrades and roguelite progression",
                "body": (
                    "XP earned by provoking villagers feeds a levelling system that opens card-based ability "
                    "choices mid-run. The card interface is not a ready-made UI package — it is generated "
                    "entirely from code at runtime."
                ),
            },
            {
                "heading": "NavMesh-based AI and procedural VFX",
                "body": (
                    "Villager movement is solved on a NavMesh, and most of the effects — disease spreading, "
                    "panic, retaliation — are procedural VFX grown directly from code rather than authored in "
                    "the Shuriken editor."
                ),
            },
            {
                "heading": "More than 90 custom Editor tools",
                "body": (
                    "I wrote over 90 custom Unity Editor tools to build and balance the village quickly inside "
                    "the scene — on a project developed alone, those tools turned out to be as valuable an "
                    "investment as the gameplay itself."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/rageattack/village-overview.jpg", "alt": "The village seen from above"},
            {"src": "portfolio/img/rageattack/village-stressbars.jpg", "alt": "Live stress and rage bars above the villagers"},
            {"src": "portfolio/img/rageattack/card-selection.jpg", "alt": "The card-based ability selection screen"},
            {"src": "portfolio/img/rageattack/editor-scenebuild.jpg", "alt": "Building the scene with custom Editor tools"},
        ],
        "highlights": [
            "5-stage rage FSM", "NavMesh AI", "Procedural VFX", "90+ Editor tools",
            "Card-based roguelite progression", "Runtime-generated UI",
        ],
        "closing": (
            "Choosing emergent chaos over scripted set pieces — designing every NPC as an independent agent "
            "reacting to stress, line of sight and each other — is the core of this project."
        ),
    },
    {
        "slug": "belgelendirme",
        "name": "Certification Platform",
        "kind": "Web Platform (PHP)",
        "tagline": "A certification and audit management platform, written without a framework on purpose.",
        "accent": "#6c63ff",
        "year": "Web Application",
        "cover": "portfolio/img/belgelendirme/dashboard.jpg",
        "summary": (
            "An end-to-end platform running the entire workflow of a certification and audit body: company "
            "records, certificate lifecycles, audit planning, expense tracking and reporting all come together "
            "in one system. Instead of Laravel or Symfony, raw PDO and procedural PHP were chosen deliberately."
        ),
        "stats": [
            {"value": "20", "label": "MySQL Tables"},
            {"value": "5", "label": "User Roles"},
            {"value": "0", "label": "PHP Frameworks"},
            {"value": "2", "label": "Composer Libraries"},
        ],
        "stack": [
            "PHP 8 (Procedural)", "PDO — Real Prepared Statements", "MySQL", "Composer",
            "Monolog 3.9", "PHPMailer 6.10", "Vanilla JavaScript", "AJAX Mini-SPA Pages",
        ],
        "sections": [
            {
                "heading": "Deliberately framework-free",
                "body": (
                    "Instead of Laravel or Symfony I chose raw PDO, procedural PHP and hand-written vanilla JS "
                    "components. Each page is a small self-contained \"mini-SPA\" hosting its own AJAX "
                    "endpoints. The goal: a system that deploys quickly onto shared hosting with minimum "
                    "dependencies, and that a single engineer can hold entirely in their head."
                ),
            },
            {
                "heading": "Five roles, one session model",
                "body": (
                    "Admin, accounting manager, auditor and consultant — five distinct roles are managed from "
                    "a single session and security core, with the control panel redirecting itself according "
                    "to the role that logged in."
                ),
            },
            {
                "heading": "The certificate lifecycle",
                "body": (
                    "I built a complete certification lifecycle starting from the company registry and moving "
                    "through accreditation type, certifying body, document number, scope and validity dates — "
                    "the tracking tower flags expiring and expired certificates instantly."
                ),
            },
            {
                "heading": "The data layer",
                "body": (
                    "20 tables on MySQL, mostly in 3NF with selective foreign keys. Evidence files are stored "
                    "as binary (LONGBLOB), while frequently-read counters use deliberately denormalised fields "
                    "for performance."
                ),
            },
            {
                "heading": "Audit planning, expenses and reporting",
                "body": (
                    "Audit scheduling through the auditor field, tracking of consultancy and training expenses "
                    "and collections, a compliance reporting panel, and a correspondence engine running on "
                    "Monolog and PHPMailer — all built on the same session and authorisation core."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/belgelendirme/dashboard.jpg", "alt": "The dashboard, routed by user role"},
            {"src": "portfolio/img/belgelendirme/tracking.jpg", "alt": "The tracking tower — expiring and expired certificates"},
        ],
        "highlights": [
            "Framework-free architecture", "5 roles / one session", "20-table MySQL schema",
            "LONGBLOB evidence storage", "Monolog + PHPMailer", "AJAX-based mini-SPA pages",
        ],
        "closing": (
            "A complex business process does not necessarily require a large framework — properly layered "
            "procedural PHP can build a maintainable system even under shared hosting constraints."
        ),
    },
]

# Every fixed word the templates say on their own, so a template never has to
# ask which language it is in.
UI = {
    "nav_home": "Home",
    "nav_cv": "CV",
    "nav_projects": "Projects",
    "nav_contact": "Contact",
    "site_description": "Uğur Polat — Computer Engineer, game and web developer. Java, Unity, PHP and Django projects.",
    "footer": "Built from scratch with Django.",
    "title_home": "Home",
    "cta_projects": "See My Projects",
    "cta_contact": "Get In Touch",
    "scroll_cue": "scroll down ↓",
    "kicker_about": "About me",
    "heading_about": "So who is Uğur?",
    "kicker_education": "Education",
    "heading_education": "Where I studied",
    "kicker_skills": "Languages & tools I know",
    "heading_skills": "Skills",
    "kicker_work": "What I have built",
    "heading_work": "4 Projects Built End to End",
    "kicker_contact": "Contact",
    "heading_contact": "Let's talk",
    "contact_prose": "I am open to new opportunities and collaborations — reach me through any of the channels below.",
    "title_projects": "Projects",
    "projects_eyebrow": "Time to show off",
    "projects_tagline": "Two Unity games, a framework-free PHP platform and a hand-written Java game — all four from scratch, end to end.",
    "card_cta": "Take a look →",
    "originality_label": "Originality note:",
    "kicker_stack": "Technologies Used",
    "kicker_gallery": "Screenshots",
    "back_to_projects": "← All Projects",
}
