"""
Static content for the CV site: no database, this is a personal portfolio
that doesn't change at runtime, so a plain module beats models + migrations.
"""

PROFILE = {
    "name": "Uğur Polat",
    "role": "Computer Engineer & Full-Stack Developer",
    "tagline": "From Java to Django, from Unity to framework-free PHP — in every project I chose to build it from scratch.",
    "age": 23,
    "location": "Istanbul, Bahçelievler",
    "phone": "0537 496 19 15",
    "email": "ugurplt8@gmail.com",
    "status": "Currently studying at Ecole 42 Istanbul",
    "bio": [
        "I hold a Computer Engineering degree from Bolu Abant İzzet Baysal University. I am currently in Istanbul, continuing my education at Ecole 42 — a fully project-based curriculum where peer evaluation replaces grades and teachers.",
        "From a Java tower defense game I wrote for the desktop, to two separate Unity games, to a PHP management platform written from scratch without a framework, to the very Django site you are looking at right now — I prefer to understand the fundamentals first and build on top of them. All four projects below came out of that approach, and every line of them is mine.",
        "I always enjoy learning something new, which is exactly why Ecole 42's peer-to-peer structure suits me — it forces both learning and working inside a team. Alongside software development, I also have work experience in different industries. I live in Bahçelievler, Istanbul.",
    ],
    "education": [
        {
            "school": "Ecole 42 Istanbul",
            "degree": "Software Engineering — Project-Based Education",
            "period": "In progress",
            "note": "No grades, no teachers: a fully peer-to-peer, project-driven curriculum.",
        },
        {
            "school": "Bolu Abant İzzet Baysal University",
            "degree": "Computer Engineering",
            "period": "Graduated",
            "note": "Various software projects, including a Java tower defense game I wrote from scratch during my final year.",
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
                {"name": "Django", "level": "Advanced"},
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
        "A tower defense game written in Java + Swing with no game engine at all",
        "A 20-table certification platform written on raw PDO, without Laravel or Symfony",
        "A Unity game built on NavMesh-based AI and 90+ custom Editor tools",
    ],
    "soft_skills": [
        "I enjoy learning", "I work well in a team",
        "Curious and research-driven", "I like turning ideas into systems from scratch",
    ],
}

PROJECTS = [
    {
        "slug": "oyunum",
        "name": "Tower Defense Game (Java)",
        "kind": "Desktop Game",
        "tagline": "A tower defense game written from scratch in Java, without any external game engine.",
        "accent": "#f2b84c",
        "year": "University Project",
        "cover": "portfolio/img/oyunum/combat.jpg",
        "summary": (
            "A tower defense game I built end to end during my computer engineering studies at Bolu "
            "Abant İzzet Baysal University, using nothing but Java and Swing — no Unity, no Godot, no "
            "ready-made engine of any kind. From the sprite engine that draws the map to the AI that "
            "drives the enemy waves, every single line came out of my own hands."
        ),
        "originality": (
            "There is no engine, no framework and no third-party library underneath this game. The game "
            "loop, the renderer, the state machine, the map editor and the save format were all designed "
            "and written by me."
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
            "Event-Driven Input Handling", "File I/O Save & Load",
        ],
        "credits": [
            "Every line of game code — written by me",
            "Custom sprite renderer on AWT Graphics2D and BufferedImage — built by me",
            "Scene state machine, game loop and threading model — designed by me",
            "Tile-based map editor and its own save file format — written by me",
            "Wave, enemy, tower, missile and map managers — all hand-written",
        ],
        "sections": [
            {
                "heading": "A scene-based state machine",
                "body": (
                    "I built a state machine that drives the transitions between the Menu, Playing, Game "
                    "Over and Editing (map editor) scenes through a shared SahneMethotlari interface. Each "
                    "scene owns its update and render loop, while the shared flow is orchestrated from a "
                    "single place."
                ),
            },
            {
                "heading": "Two threads, one consistent rhythm",
                "body": (
                    "Game logic updates and screen rendering advance on separate rhythms and are "
                    "synchronised for a consistent frame rate. This was the first project where I solved a "
                    "real multithreading problem end to end inside an actual product."
                ),
            },
            {
                "heading": "Responsibilities split into manager layers",
                "body": (
                    "Every responsibility lives in its own manager class: DalgaYoneticisi (waves), "
                    "DusmanYoneticisi (enemies), KuleYoneticisi (towers), FuzeYoneticisi (missiles) and "
                    "HaritaYoneticisi (map). Enemy types such as Orc and Bat derive from a shared Dusman "
                    "base class, so thanks to inheritance and polymorphism, adding a new enemy is just "
                    "writing one more subclass."
                ),
            },
            {
                "heading": "My own map editor",
                "body": (
                    "I wrote a tile-based editor: start/end points and the path can be laid out freely, "
                    "saved to disk in my own file format and loaded back through the YukleKaydet class. "
                    "The map you see in the screenshots was designed with that editor."
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
            "Building a complex system by splitting it into independent, testable parts — that is the most "
            "lasting skill this project gave me."
        ),
    },
    {
        "slug": "kitchen-rush",
        "name": "Kitchen Rush",
        "kind": "Unity Game",
        "tagline": "A solo-developed kitchen simulation built entirely around real-time pressure.",
        "accent": "#ff7a45",
        "year": "Unity Solo Project",
        "cover": "portfolio/img/kitchenrush/kitchen-1.jpg",
        "summary": (
            "A single-player kitchen management game inspired by Overcooked. The player runs between "
            "stations, chops ingredients, cooks them and races to get orders onto the counter before "
            "time runs out. I developed it in Unity from start to finish, entirely on my own."
        ),
        "originality": (
            "Every 3D model, texture, animation and interface design in this game was produced from "
            "scratch by my own hands — no asset packs, no third-party tools."
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
        "credits": [
            "All 17 C# runtime scripts — written by me",
            "Every 3D model, PBR texture and material — modelled and textured by me",
            "All character animations and the rig pipeline — set up by me",
            "The entire UI — designed and built by me",
            "7 NUnit unit tests and the custom Editor tools — written by me",
            "Game design, balancing and timing tuning — done by me",
        ],
        "sections": [
            {
                "heading": "The gameplay loop",
                "body": (
                    "I built a continuous pressure loop between order generation, ingredient preparation "
                    "(chopping/cooking), delivery and scoring: 15 seconds between orders, 3 seconds of "
                    "cooking time on the stove — tuned to keep the player permanently in motion."
                ),
            },
            {
                "heading": "Code architecture through IInteractable",
                "body": (
                    "Six different station types — chopping board, stove, sink and the rest — all share a "
                    "single IInteractable interface. Adding a new station means writing one class that "
                    "implements that interface; the gameplay code never knows the concrete type of the "
                    "station it is talking to."
                ),
            },
            {
                "heading": "Input, movement and animation",
                "body": (
                    "Three input actions (Move, Interact, Cut) are defined through Unity's new Input "
                    "System. The character moves with a two-state Animator (Idle/Walking) fed by a Mixamo "
                    "rig pipeline."
                ),
            },
            {
                "heading": "Visual feedback without leaks",
                "body": (
                    "To keep the highlight system independent of the shader, I used MaterialPropertyBlock "
                    "— a lightweight layer written to the GPU in one pass, instead of spawning a new "
                    "material instance every frame and leaking memory. Timings such as cooking duration "
                    "run on coroutines."
                ),
            },
            {
                "heading": "Testing and quality assurance",
                "body": (
                    "Alongside the 16 runtime scripts I wrote 7 NUnit unit tests; this was the project "
                    "where I first took testing culture seriously inside a Unity project."
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
            "Interface-first design and the editor + runtime dual-trigger automation decisions showed me "
            "how much clean architecture matters, even in a small game."
        ),
    },
    {
        "slug": "rage-attack",
        "name": "Rage Attack",
        "kind": "Unity Game",
        "tagline": "One fly, one village, a full nervous breakdown — a chaos game built on systemic AI.",
        "accent": "#b25cff",
        "year": "Unity Solo Project",
        "cover": "portfolio/img/rageattack/village-overview.jpg",
        "summary": (
            "The player takes the role of a housefly (or RobotSinek, the robotic fly unlocked later) and "
            "swarms an entire village. Every NPC walking the scene runs an independent stress simulation "
            "and escalates through a five-stage rage state machine, from mild irritation all the way to "
            "arming themselves and attacking. The real engineering problem was making that simulation "
            "feel alive and out of control."
        ),
        "originality": (
            "Every piece of design and animation in this game — from the village buildings to the "
            "character animations, from the procedural VFX to the interface — was produced by me, with "
            "no asset packs and no third-party tools."
        ),
        "stats": [
            {"value": "5", "label": "Stage Rage FSM"},
            {"value": "2", "label": "Playable Characters"},
            {"value": "90+", "label": "Custom Editor Tools"},
            {"value": "0", "label": "Scripted Setpieces"},
        ],
        "stack": [
            "Unity (URP)", "C#", "NavMesh AI", "New Input System", "TextMeshPro", "ScriptableObject",
            "Custom Editor Tools", "Coroutine State Machines", "Procedural VFX",
            "Runtime-Generated UI", "Mixamo Rig Pipeline", "PlayerPrefs",
        ],
        "credits": [
            "All C# gameplay code, including the five-stage rage FSM — written by me",
            "90+ custom Unity Editor tools — written by me",
            "Every building, character and animation in the village — produced by me",
            "Procedural VFX driven straight from code — written by me, no Shuriken presets",
            "The card-based upgrade UI, generated at runtime in pure code — built by me",
            "Systemic AI design, NavMesh setup and balancing — done by me",
        ],
        "sections": [
            {
                "heading": "Nerve management: a five-stage NPC rage FSM",
                "body": (
                    "Every character in the village runs its own rage state machine: starting at mild "
                    "irritation and escalating through being triggered, panic and finally arming up and "
                    "attacking, across five stages. There is no scripted setpiece — every brawl, every "
                    "riot, every stampede is a side effect of these independent state machines seeing "
                    "each other and the player."
                ),
            },
            {
                "heading": "Two playable characters, completely different kits",
                "body": (
                    "You can choose between the default agile fly and the stronger RobotSinek, unlocked "
                    "through in-game kill count; their ability kits are different from top to bottom."
                ),
            },
            {
                "heading": "XP, card-based upgrades and roguelite progression",
                "body": (
                    "XP earned by provoking villagers feeds a level-up system that opens card-based "
                    "ability choices mid-run. The card interface is not a ready-made UI package — it is "
                    "generated entirely at runtime from code."
                ),
            },
            {
                "heading": "NavMesh-based AI and procedural VFX",
                "body": (
                    "Villager movement is solved on a NavMesh; most of the effects — disease spread, "
                    "panic, retaliation — are produced with procedural VFX grown directly from code "
                    "rather than authored in the Shuriken editor."
                ),
            },
            {
                "heading": "More than 90 custom Editor tools",
                "body": (
                    "To lay out and balance the village quickly inside the scene, I wrote over 90 custom "
                    "Unity Editor tools — on a project I developed alone, those tools turned out to be "
                    "the investment that saved the most time, as important as the gameplay itself."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/rageattack/village-overview.jpg", "alt": "Overview of the village"},
            {"src": "portfolio/img/rageattack/village-stressbars.jpg", "alt": "Live stress/rage bars above the villagers"},
            {"src": "portfolio/img/rageattack/card-selection.jpg", "alt": "Card-based ability selection screen"},
            {"src": "portfolio/img/rageattack/editor-scenebuild.jpg", "alt": "Building the scene with my custom Editor tools"},
        ],
        "highlights": [
            "5-stage rage FSM", "NavMesh AI", "Procedural VFX", "90+ Editor tools",
            "Card-based roguelite progression", "Runtime-generated UI",
        ],
        "closing": (
            "Choosing emergent chaos over scripted setpieces — designing every NPC as an independent "
            "agent that reacts to stress, line of sight and each other — is the core of this project."
        ),
    },
    {
        "slug": "belgelendirme",
        "name": "Certification Platform",
        "kind": "Web Platform (PHP)",
        "tagline": "A certification & audit management platform, deliberately written without a framework.",
        "accent": "#6c63ff",
        "year": "Web Application",
        "cover": "portfolio/img/belgelendirme/dashboard.jpg",
        "summary": (
            "An end-to-end platform that runs the entire workflow of a certification/audit body: company "
            "records, certificate lifecycle, audit planning, expense tracking and reporting all come "
            "together in a single system. Instead of Laravel or Symfony, I deliberately chose raw PDO "
            "and procedural PHP."
        ),
        "originality": (
            "No framework, no scaffolding, no UI kit. Every line of PHP, SQL and JavaScript in this "
            "platform was written by me; the only third-party code in it is two Composer packages."
        ),
        "stats": [
            {"value": "20", "label": "MySQL Tables"},
            {"value": "5", "label": "User Roles"},
            {"value": "0", "label": "PHP Frameworks"},
            {"value": "2", "label": "Composer Packages"},
        ],
        "stack": [
            "PHP 8 (Procedural)", "PDO — Real Prepared Statements", "MySQL", "Composer",
            "Monolog 3.9", "PHPMailer 6.10", "Vanilla JavaScript", "AJAX Mini-SPA Pages",
        ],
        "credits": [
            "Every line of PHP, SQL and JavaScript — written by me",
            "The 20-table MySQL schema — designed and normalised by me",
            "Session and authorization core for 5 roles — hand-rolled, no auth library",
            "All AJAX mini-SPA components — hand-written in vanilla JS",
            "Reporting panel and the Monolog + PHPMailer correspondence engine — wired up by me",
        ],
        "sections": [
            {
                "heading": "Deliberately framework-free",
                "body": (
                    "Instead of Laravel or Symfony I chose raw PDO, procedural PHP and hand-written "
                    "vanilla JS components. Every page is a small, self-contained \"mini-SPA\" that hosts "
                    "its own AJAX endpoints. The goal: a system that can be deployed quickly on shared "
                    "hosting with minimal dependencies, and that a single engineer can hold entirely in "
                    "their head."
                ),
            },
            {
                "heading": "Five roles, one session model",
                "body": (
                    "Five different roles — admin, finance manager, auditor and consultant among them — "
                    "are managed from a single session and security core; the dashboard redirects itself "
                    "based on the role that logged in."
                ),
            },
            {
                "heading": "The lifecycle of a certificate",
                "body": (
                    "I built full certification lifecycle management, starting from the company registry "
                    "and moving through accreditation type, certifying body, certificate number, scope "
                    "and validity dates — the tracking tower instantly flags certificates that are "
                    "expiring or already expired."
                ),
            },
            {
                "heading": "The data layer",
                "body": (
                    "20 tables on MySQL, designed mostly in 3NF with selective foreign keys. Binary "
                    "(LONGBLOB) storage is used for evidence files, and denormalised fields are used for "
                    "frequently read counters where performance mattered."
                ),
            },
            {
                "heading": "Audit planning, expense tracking and reporting",
                "body": (
                    "Audit planning driven by the auditor field, tracking of consultancy/training "
                    "expenses and collections, a compliance reporting panel, and a correspondence engine "
                    "running on Monolog + PHPMailer — all built on the same session and authorization "
                    "core."
                ),
            },
        ],
        "gallery": [
            {"src": "portfolio/img/belgelendirme/dashboard.jpg", "alt": "Role-aware dashboard"},
            {"src": "portfolio/img/belgelendirme/tracking.jpg", "alt": "Certificate tracking tower — expiring and expired certificates"},
        ],
        "highlights": [
            "Framework-free architecture", "5 roles / one session", "20-table MySQL schema",
            "LONGBLOB evidence storage", "Monolog + PHPMailer", "AJAX-based mini-SPA pages",
        ],
        "closing": (
            "A complex business process does not necessarily require a large framework — properly "
            "layered procedural PHP can build a maintainable system even under shared hosting limits."
        ),
    },
]


def get_project(slug):
    for project in PROJECTS:
        if project["slug"] == slug:
            return project
    return None
