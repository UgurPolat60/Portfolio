/* Anatomy of a laptop.
 *
 * Every part in this scene is generated from primitives at load time — there is
 * no .glb, no .obj, nothing downloaded but three.js itself. That is deliberate:
 * the whole site is served by a web server I wrote in C, and a model file would
 * have been the one thing on the page I had not made.
 *
 * The look is doing three things at once: a studio environment built out of
 * emissive panels (metal has nothing to reflect otherwise), soft shadow maps,
 * and a post chain that tone-maps, blooms the specular highlights and cleans
 * the edges. The only input the scene takes is how far down the page you are.
 */

import * as THREE from 'three';
import { RoundedBoxGeometry } from 'three/addons/geometries/RoundedBoxGeometry.js';
import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

const canvas = document.getElementById('stage');
const pin = document.getElementById('pin');
const bar = document.getElementById('bar');
const chapters = [...document.querySelectorAll('.chapters button')];
const reduced = matchMedia('(prefers-reduced-motion: reduce)').matches;

let renderer;
try {
	renderer = new THREE.WebGLRenderer({ canvas, antialias: false, alpha: false, powerPreference: 'high-performance' });
} catch (e) {
	document.getElementById('nowebgl').hidden = false;
	throw e;
}
/* Resolution is not fixed. It starts at 1 and the frame loop moves it up
   towards the display's native ratio when there is headroom, or down when
   frames start arriving late — see adapt(). */
const DPR_MAX = Math.min(devicePixelRatio, 2);
const DPR_MIN = 0.7;
let dpr = Math.min(DPR_MAX, 1);

renderer.setPixelRatio(dpr);
renderer.setSize(innerWidth, innerHeight);
renderer.toneMapping = THREE.ACESFilmicToneMapping;
renderer.toneMappingExposure = 1.0;
renderer.shadowMap.enabled = true;
/* PCF rather than PCFSoft: the soft variant costs several times more per
   fragment and the difference is invisible at this shadow's softness. */
renderer.shadowMap.type = THREE.PCFShadowMap;
/* The shadow map is only re-rendered when something actually moved. */
renderer.shadowMap.autoUpdate = false;
renderer.shadowMap.needsUpdate = true;

const scene = new THREE.Scene();
scene.background = new THREE.Color(0x06080b);

const camera = new THREE.PerspectiveCamera(34, innerWidth / innerHeight, 0.5, 400);

/* ---------------------------------------------------------- the light room */

/* A product shot is mostly reflections. This is a dark room with a few large
   bright panels in it — an overhead softbox for the long streak down the lid,
   two side fills to pick out the edges, and a mint strip behind that ties the
   scene to the rest of the site. Rendered once into an environment map. */
function studio() {
	const env = new THREE.Scene();

	/* A polished metal surface shows you the room, not itself. So the room has
	   to be worth reflecting: a mid-grey shell rather than a black one, or the
	   laptop just comes out looking like a silhouette. */
	const shell = new THREE.Mesh(
		new THREE.SphereGeometry(60, 32, 24),
		new THREE.MeshBasicMaterial({ side: THREE.BackSide })
	);
	shell.material.color.setRGB(0.30, 0.33, 0.38);
	env.add(shell);

	/* the floor of the light room, darker, so the underside stays grounded */
	const floor = new THREE.Mesh(new THREE.PlaneGeometry(140, 140),
		new THREE.MeshBasicMaterial({ side: THREE.DoubleSide }));
	floor.material.color.setRGB(0.06, 0.07, 0.09);
	floor.rotation.x = -Math.PI / 2;
	floor.position.y = -3;
	env.add(floor);

	const panel = (w, h, r, g, b) => {
		const m = new THREE.Mesh(
			new THREE.PlaneGeometry(w, h),
			new THREE.MeshBasicMaterial({ side: THREE.DoubleSide })
		);
		m.material.color.setRGB(r, g, b);   /* above 1 on purpose: this is HDR */
		env.add(m);
		return m;
	};

	/* the big overhead softbox — this is the long streak that runs down the lid */
	const key = panel(46, 20, 14, 14.4, 15.5);
	key.position.set(-6, 26, 6);
	key.rotation.x = Math.PI / 2;

	/* a second, narrower one gives the edges a defined highlight line */
	const kicker = panel(60, 3.5, 9, 9.4, 10.5);
	kicker.position.set(0, 21, -10);
	kicker.rotation.x = Math.PI / 2;

	const fillL = panel(26, 34, 3.4, 3.7, 4.4);
	fillL.position.set(-44, 9, 4);
	fillL.rotation.y = Math.PI / 2;

	const fillR = panel(20, 28, 2.0, 2.2, 2.7);
	fillR.position.set(44, 8, -6);
	fillR.rotation.y = -Math.PI / 2;

	/* mint strip behind, so the scene belongs to the rest of the site */
	const rim = panel(38, 7, 0.5, 3.2, 2.2);
	rim.position.set(0, 9, -46);

	return env;
}

const pmrem = new THREE.PMREMGenerator(renderer);
pmrem.compileEquirectangularShader();
scene.environment = pmrem.fromScene(studio(), 0.03).texture;
/* Turned down: the panels need to stay bright so metal has a hot reflection to
   catch, but at full strength that same light floods the matte surfaces and a
   near-black circuit board comes out the colour of sage. */
scene.environmentIntensity = 0.5;

/* One shadow-casting light. Everything else is the environment. */
const sun = new THREE.DirectionalLight(0xffffff, 2.4);
sun.position.set(-16, 30, 16);
sun.castShadow = true;
sun.shadow.mapSize.set(1536, 1536);
sun.shadow.camera.near = 5;
sun.shadow.camera.far = 90;
sun.shadow.camera.left = -30;
sun.shadow.camera.right = 30;
sun.shadow.camera.top = 30;
sun.shadow.camera.bottom = -30;
sun.shadow.bias = -0.0006;
sun.shadow.normalBias = 0.03;
sun.shadow.radius = 3;
scene.add(sun);

/* a soft mint bounce from behind, echoing the site's accent without blowing out */
const accent = new THREE.DirectionalLight(0x66d9a0, 0.5);
accent.position.set(20, 8, -26);
scene.add(accent);

/* ------------------------------------------------------------- materials */

const physical = (o) => new THREE.MeshPhysicalMaterial(o);
const standard = (o) => new THREE.MeshStandardMaterial(o);

const M = {
	/* anodised aluminium: brushed, so it streaks rather than mirrors */
	alu: physical({ color: 0x30353d, metalness: 1.0, roughness: 0.30, anisotropy: 0.6, anisotropyRotation: 0 }),
	aluDark: physical({ color: 0x1a1e25, metalness: 1.0, roughness: 0.42, anisotropy: 0.4 }),
	deck: physical({ color: 0x272c34, metalness: 1.0, roughness: 0.34, anisotropy: 0.55, transparent: true }),
	key: physical({ color: 0x0d1014, metalness: 0.1, roughness: 0.62, clearcoat: 0.35, clearcoatRoughness: 0.5, transparent: true }),
	glass: physical({ color: 0x04060a, metalness: 0.0, roughness: 0.045, clearcoat: 1.0, clearcoatRoughness: 0.03 }),
	screen: new THREE.MeshBasicMaterial({ color: 0x0b241f }),
	/* laptop boards are dark: solder mask over ten layers of copper, not the
	   bright green of a hobby PCB */
	pcb: standard({ color: 0x06211a, metalness: 0.0, roughness: 0.78 }),
	pcbDark: standard({ color: 0x041610, metalness: 0.0, roughness: 0.8 }),
	solder: physical({ color: 0xb9bec6, metalness: 1.0, roughness: 0.34 }),
	ihs: physical({ color: 0xd2d8e0, metalness: 1.0, roughness: 0.12, clearcoat: 0.4 }),
	die: physical({ color: 0x2a3038, metalness: 0.35, roughness: 0.22, clearcoat: 0.8, clearcoatRoughness: 0.1 }),
	copper: physical({ color: 0x8a5228, metalness: 1.0, roughness: 0.33, anisotropy: 0.3 }),
	gold: physical({ color: 0xc9a13a, metalness: 1.0, roughness: 0.26 }),
	fan: physical({ color: 0x14171d, metalness: 0.3, roughness: 0.5, clearcoat: 0.4 }),
	blade: physical({ color: 0x2a2f37, metalness: 0.2, roughness: 0.45, clearcoat: 0.5 }),
	battery: physical({ color: 0x1c2028, metalness: 0.2, roughness: 0.55, clearcoat: 0.25 }),
	chip: physical({ color: 0x121519, metalness: 0.25, roughness: 0.4, clearcoat: 0.5, clearcoatRoughness: 0.25 }),
	trace: standard({ color: 0x7d6224, metalness: 0.95, roughness: 0.42 }),
};

const rbox = (w, h, d, r = 0.06, seg = 3) =>
	new RoundedBoxGeometry(w, h, d, seg, Math.min(r, Math.min(w, h, d) / 2 - 0.001));

function mesh(geo, mat, x = 0, y = 0, z = 0) {
	const m = new THREE.Mesh(geo, mat);
	m.position.set(x, y, z);
	m.castShadow = true;
	m.receiveShadow = true;
	return m;
}

/* Small parts still receive shadow but stop casting it. Every caster is drawn
   a second time into the shadow map, and a two-millimetre capacitor's shadow
   is not worth a draw call. */
function noCast(m) {
	m.castShadow = false;
	m.receiveShadow = true;
	return m;
}

const dummy = new THREE.Object3D();

/* one instanced mesh instead of N meshes: N draw calls become one */
function instance(geo, mat, count, place) {
	const im = new THREE.InstancedMesh(geo, mat, count);
	for (let i = 0; i < count; i++) {
		dummy.position.set(0, 0, 0);
		dummy.rotation.set(0, 0, 0);
		dummy.scale.set(1, 1, 1);
		place(i, dummy);
		dummy.updateMatrix();
		im.setMatrixAt(i, dummy.matrix);
	}
	return noCast(im);
}

/* --------------------------------------------------------------- the laptop */

const laptop = new THREE.Group();
scene.add(laptop);

const BASE_W = 35.6, BASE_D = 25.4, BASE_H = 1.75;
const FLOOR = 0.34;                       /* thickness of the bottom panel */
const MB_Y = FLOOR + 0.12;                /* board sits just above the floor */
const MB_TOP = MB_Y + 0.09;
const CPU = new THREE.Vector3(-4.6, MB_TOP, -8.2);
const GPU = new THREE.Vector3(5.6, MB_TOP, -8.2);
const RAM = new THREE.Vector3(11.4, MB_TOP, -2.2);
const DECK_Y = BASE_H - 0.12;

const base = new THREE.Group();
laptop.add(base);

/* ---- chassis: a tray with rounded outside corners */
base.add(mesh(rbox(BASE_W, FLOOR, BASE_D, 0.16), M.aluDark, 0, FLOOR / 2, 0));
const wallH = BASE_H - FLOOR;
const wallY = FLOOR + wallH / 2;
base.add(mesh(rbox(BASE_W, wallH, 0.5, 0.12), M.alu, 0, wallY, -BASE_D / 2 + 0.25));
base.add(mesh(rbox(BASE_W, wallH, 0.5, 0.12), M.alu, 0, wallY, BASE_D / 2 - 0.25));
base.add(mesh(rbox(0.5, wallH, BASE_D, 0.12), M.alu, -BASE_W / 2 + 0.25, wallY, 0));
base.add(mesh(rbox(0.5, wallH, BASE_D, 0.12), M.alu, BASE_W / 2 - 0.25, wallY, 0));

/* rear exhaust slots */
base.add(instance(rbox(0.3, 0.66, 0.16, 0.06), M.aluDark, 22, (i, d) => {
	const side = i < 11 ? -1 : 1;
	d.position.set(side * 11.5 + ((i % 11) - 5) * 0.46, wallY + 0.05, -BASE_D / 2 + 0.02);
}));

/* rubber feet, because a real one has them and the shadow needs the gap */
for (const [fx, fz] of [[-14, -9.5], [14, -9.5], [-14, 9.5], [14, 9.5]]) {
	base.add(mesh(rbox(2.6, 0.16, 0.9, 0.07), M.chip, fx, 0.05, fz));
}

/* ---- mainboard */
const boardGrp = new THREE.Group();
base.add(boardGrp);
const board = mesh(rbox(29.5, 0.18, 10.6, 0.1), M.pcb, 0, MB_Y, -6.4);
boardGrp.add(board);

/* surface-mount detail: regulators, chokes, capacitors */
const smdGeo = rbox(0.62, 0.3, 0.62, 0.05);
const smd = noCast(new THREE.InstancedMesh(smdGeo, M.chip, 90));
let n = 0;
for (let i = 0; i < 150 && n < 90; i++) {
	const x = -13.6 + (i % 25) * 1.14;
	const z = -11.2 + Math.floor(i / 25) * 0.96;
	if (Math.abs(x - CPU.x) < 3.1 && Math.abs(z - CPU.z) < 3.1) continue;
	if (Math.abs(x - GPU.x) < 3.6 && Math.abs(z - GPU.z) < 3.1) continue;
	if (x > 7.4 && z > -4.4) continue;                       /* keep the RAM area clear */
	dummy.position.set(x, MB_TOP + 0.12, z);
	dummy.rotation.set(0, (i % 4) * 0.35, 0);
	dummy.scale.set(0.55 + (i % 5) * 0.22, 0.5 + (i % 3) * 0.55, 0.55 + (i % 4) * 0.2);
	dummy.updateMatrix();
	smd.setMatrixAt(n++, dummy.matrix);
}
smd.count = n;
boardGrp.add(smd);

/* copper traces, kept inside the board outline: bus runs between the packages
   rather than decoration scattered over the chassis */
const BX = 14.2, BZ0 = -11.4, BZ1 = -1.6;
const traceGeo = rbox(1, 0.012, 0.045, 0.004, 1);
const traces = noCast(new THREE.InstancedMesh(traceGeo, M.trace, 76));
for (let i = 0; i < 76; i++) {
	const along = i % 4 !== 0;                       /* most runs go left-right */
	const len = along ? 2.5 + (i % 7) * 1.3 : 1.2 + (i % 5) * 0.8;
	const half = len / 2;
	const x = Math.max(-BX + half, Math.min(BX - half, -13 + ((i * 5.3) % 27)));
	const z = Math.max(BZ0 + 0.3, Math.min(BZ1 - 0.3, BZ0 + ((i * 1.7) % 9.4)));
	dummy.position.set(x, MB_TOP + 0.015, z);
	dummy.rotation.set(0, along ? 0 : Math.PI / 2, 0);
	dummy.scale.set(len, 1, 1);
	dummy.updateMatrix();
	traces.setMatrixAt(i, dummy.matrix);
}
boardGrp.add(traces);

/* ---- CPU package: substrate, die, integrated heat spreader */
const cpuGrp = new THREE.Group();
cpuGrp.position.copy(CPU);
boardGrp.add(cpuGrp);
cpuGrp.add(mesh(rbox(3.1, 0.14, 3.1, 0.04), M.pcbDark, 0, 0.07, 0));
cpuGrp.add(mesh(rbox(1.75, 0.08, 1.75, 0.02), M.die, 0, 0.18, 0));
const cpuIhs = mesh(rbox(2.5, 0.22, 2.5, 0.08), M.ihs, 0, 0.33, 0);
cpuGrp.add(cpuIhs);

/* ---- GPU: bigger bare die, ringed by its own memory packages */
const gpuGrp = new THREE.Group();
gpuGrp.position.copy(GPU);
boardGrp.add(gpuGrp);
gpuGrp.add(mesh(rbox(4.4, 0.14, 3.6, 0.05), M.pcbDark, 0, 0.07, 0));
gpuGrp.add(mesh(rbox(2.5, 0.2, 2.1, 0.03), M.die, 0, 0.24, 0));
for (const [mx, mz] of [[-1.7, -1.3], [1.7, -1.3], [-1.7, 1.3], [1.7, 1.3]]) {
	gpuGrp.add(mesh(rbox(1.05, 0.16, 0.95, 0.04), M.chip, mx, 0.22, mz));
}

/* ---- memory: two modules in stacked slots, angled to save height */
const ramGrp = new THREE.Group();
ramGrp.position.copy(RAM);
boardGrp.add(ramGrp);
for (let i = 0; i < 2; i++) {
	const stick = new THREE.Group();
	stick.position.set(0, i * 0.42, -i * 0.3);
	stick.rotation.x = -0.14;
	stick.add(mesh(rbox(7.0, 0.12, 3.0, 0.04), M.pcbDark, 0, 0, 0));
	for (let c = 0; c < 4; c++) {
		stick.add(mesh(rbox(1.35, 0.14, 1.15, 0.03), M.chip, -2.5 + c * 1.66, 0.13, 0.35));
	}
	stick.add(mesh(rbox(6.5, 0.03, 0.3, 0.01), M.gold, 0, -0.02, -1.36));
	ramGrp.add(stick);
}

/* ---- storage: one M.2 stick */
boardGrp.add(mesh(rbox(2.2, 0.1, 7.4, 0.04), M.pcbDark, -12.3, MB_TOP + 0.05, -3.0));
boardGrp.add(mesh(rbox(1.5, 0.14, 1.5, 0.03), M.chip, -12.3, MB_TOP + 0.17, -4.2));
boardGrp.add(mesh(rbox(1.5, 0.14, 1.5, 0.03), M.chip, -12.3, MB_TOP + 0.17, -1.8));

/* ---- battery: the single biggest object in the box */
const batteryGrp = new THREE.Group();
base.add(batteryGrp);
for (let i = 0; i < 4; i++) {
	batteryGrp.add(mesh(rbox(6.3, 0.62, 8.2, 0.1), M.battery, -9.9 + i * 6.6, FLOOR + 0.33, 6.2));
}

/* ---- cooling: cold plates, flattened pipes, fin stacks, two blowers */
const coolGrp = new THREE.Group();
base.add(coolGrp);
const PIPE_Y = MB_TOP + 0.62;

coolGrp.add(mesh(rbox(3.6, 0.2, 3.6, 0.06), M.copper, CPU.x, MB_TOP + 0.46, CPU.z));
coolGrp.add(mesh(rbox(4.8, 0.2, 4.0, 0.06), M.copper, GPU.x, MB_TOP + 0.46, GPU.z));

const pipeGeo = new THREE.CylinderGeometry(0.19, 0.19, 25.5, 24, 1, false);
for (let i = 0; i < 4; i++) {
	const p = new THREE.Mesh(pipeGeo, M.copper);
	p.castShadow = p.receiveShadow = true;
	p.rotation.z = Math.PI / 2;
	p.scale.z = 0.55;                        /* heatpipes are pressed flat to fit */
	p.position.set(0, PIPE_Y, -7.3 - i * 0.5);
	coolGrp.add(p);
}

const finGeo = rbox(0.05, 1.15, 0.55, 0.01, 1);
const bladeGeo = rbox(0.05, 0.62, 2.05, 0.02, 1);
for (const side of [-1, 1]) {
	coolGrp.add(instance(finGeo, M.alu, 30, (f, d) => {
		d.position.set(side * 11.6 - 2.9 + f * 0.2, PIPE_Y - 0.05, -11.9);
	}));

	const fan = new THREE.Group();
	fan.position.set(side * 11.6, MB_TOP + 0.5, -8.2);
	coolGrp.add(fan);

	const shroud = new THREE.Mesh(new THREE.CylinderGeometry(3.2, 3.2, 0.9, 48, 1, true), M.fan);
	shroud.castShadow = shroud.receiveShadow = true;
	fan.add(shroud);
	const capRing = mesh(new THREE.RingGeometry(2.9, 3.25, 48), M.fan, 0, 0.45, 0);
	capRing.rotation.x = -Math.PI / 2;
	fan.add(capRing);

	const rotor = new THREE.Group();
	fan.add(rotor);
	rotor.add(noCast(mesh(new THREE.CylinderGeometry(0.85, 0.95, 0.75, 24), M.aluDark, 0, 0, 0)));
	/* 42 blades as one instanced mesh — as 42 meshes this was, on its own,
	   the single largest source of draw calls in the scene */
	rotor.add(instance(bladeGeo, M.blade, 42, (b, d) => {
		const a = (b / 42) * Math.PI * 2;
		d.position.set(Math.sin(a) * 1.95, 0, Math.cos(a) * 1.95);
		d.rotation.set(0.35, a, 0.12);
	}));
	fan.userData.rotor = rotor;
	fan.userData.dir = side;
}

/* ---- keyboard deck */
const deck = new THREE.Group();
base.add(deck);
deck.add(mesh(rbox(BASE_W - 0.9, 0.3, BASE_D - 0.9, 0.14), M.deck, 0, DECK_Y, 0));

const keyGeo = rbox(1, 0.18, 1, 0.05);
const keys = noCast(new THREE.InstancedMesh(keyGeo, M.key, 84));
let ki = 0;
const rows = [
	{ z: -7.0, cols: 15, w: 1.5 },
	{ z: -5.3, cols: 15, w: 1.5 },
	{ z: -3.6, cols: 14, w: 1.6 },
	{ z: -1.9, cols: 13, w: 1.7 },
	{ z: -0.2, cols: 9, w: 2.4 },
];
for (const row of rows) {
	const span = row.cols * row.w;
	for (let c = 0; c < row.cols && ki < 84; c++) {
		dummy.position.set(-span / 2 + row.w / 2 + c * row.w, DECK_Y + 0.22, row.z);
		dummy.rotation.set(0, 0, 0);
		/* the middle key of the bottom row is the spacebar */
		const wide = row.cols === 9 && c === 4;
		dummy.scale.set(wide ? row.w * 4.6 : row.w - 0.22, 1, 1.36);
		dummy.updateMatrix();
		keys.setMatrixAt(ki++, dummy.matrix);
	}
}
keys.count = ki;
deck.add(keys);
deck.add(mesh(rbox(9.6, 0.06, 6.0, 0.05), M.glass, 0, DECK_Y + 0.18, 5.6));

/* ---- lid, pivoting on the hinge line at the back */
const lid = new THREE.Group();
lid.position.set(0, BASE_H - 0.25, -BASE_D / 2 + 0.55);
base.add(lid);

const LID_D = 23.6;
lid.add(mesh(rbox(BASE_W, 0.5, LID_D, 0.16), M.alu, 0, 0.25, LID_D / 2));
lid.add(mesh(rbox(BASE_W - 0.8, 0.05, LID_D - 0.8, 0.05), M.glass, 0, 0.53, LID_D / 2));
const screen = mesh(rbox(BASE_W - 2.0, 0.02, LID_D - 2.4, 0.02), M.screen, 0, 0.555, LID_D / 2);
screen.castShadow = false;
lid.add(screen);

for (const side of [-1, 1]) {
	const h = new THREE.Mesh(new THREE.CylinderGeometry(0.32, 0.32, 4.2, 24), M.aluDark);
	h.castShadow = h.receiveShadow = true;
	h.rotation.z = Math.PI / 2;
	h.position.set(side * 10.5, 0.15, 0.1);
	lid.add(h);
}

/* ---- ground: invisible except for the shadow it catches, so the page keeps
   its black background instead of gaining a lit studio floor */
const ground = new THREE.Mesh(
	new THREE.PlaneGeometry(220, 220),
	new THREE.ShadowMaterial({ opacity: 0.55 })
);
ground.rotation.x = -Math.PI / 2;
ground.position.y = -0.001;
ground.receiveShadow = true;
scene.add(ground);

/* ------------------------------------------------------------------ stages */

const V = (x, y, z) => new THREE.Vector3(x, y, z);
const COOL_LIFT = 7.5;

const STAGES = [
	{ name: 'closed',  cam: V(27, 15, 44), look: V(0, 1.6, 0),      part: null,   label: '' },
	{ name: 'display', cam: V(3, 17, 46),  look: V(0, 9, -8),       part: screen, label: 'the display' },
	{ name: 'deck',    cam: V(7, 40, 34),  look: V(0, 1, -3),       part: null,   label: '' },
	{ name: 'cpu',     cam: V(-16, 19, 15), look: CPU.clone().setY(1.0), part: cpuIhs, label: 'the processor' },
	{ name: 'ram',     cam: V(27, 17, 13), look: RAM.clone().setY(1.1), part: ramGrp, label: 'the memory' },
	{ name: 'gpu',     cam: V(22, 17, 13), look: GPU.clone().setY(1.0), part: gpuGrp, label: 'the graphics chip' },
	{ name: 'cooling', cam: V(5, 25, 31),  look: V(0, COOL_LIFT + 1.2, -9.3), part: coolGrp, label: 'the cooling' },
	{ name: 'board',   cam: V(10, 42, 40), look: V(0, 1, -4),       part: boardGrp, label: 'the mainboard' },
];

const smooth = (t) => t * t * (3 - 2 * t);
const clamp01 = (v) => v < 0 ? 0 : v > 1 ? 1 : v;
const seg = (p, a, b) => smooth(clamp01((p - a) / (b - a)));

const camPos = new THREE.Vector3().copy(STAGES[0].cam);
const camLook = new THREE.Vector3().copy(STAGES[0].look);
const wantPos = new THREE.Vector3();
const wantLook = new THREE.Vector3();
const fwd = new THREE.Vector3();
const right = new THREE.Vector3();
const UP = new THREE.Vector3(0, 1, 0);

let progress = 0;
let shown = 0;
let shownPct = -1;

/* The panels are not the same height — some sections have more to say than
   others — so progress is measured against where the panels actually are
   rather than against a flat fraction of the page. Otherwise the camera
   arrives at the memory while you are still reading about the processor. */
const panels = [...document.querySelectorAll('.panel')];
let anchors = [];

function measure() {
	anchors = panels.map((el) => el.offsetTop + el.offsetHeight / 2);
}

function readScroll() {
	if (anchors.length < 2) return 0;
	const y = scrollY + innerHeight / 2;
	const last = anchors.length - 1;
	if (y <= anchors[0]) return 0;
	if (y >= anchors[last]) return 1;
	for (let i = 0; i < last; i++) {
		if (y <= anchors[i + 1]) {
			return (i + (y - anchors[i]) / (anchors[i + 1] - anchors[i])) / last;
		}
	}
	return 1;
}

function apply(p) {
	const last = STAGES.length - 1;
	const f = p * last;
	const i = Math.min(Math.floor(f), last - 1);
	const t = smooth(clamp01(f - i));

	wantPos.copy(STAGES[i].cam).lerp(STAGES[i + 1].cam, t);
	wantLook.copy(STAGES[i].look).lerp(STAGES[i + 1].look, t);

	/* slide the view sideways along the camera's own right vector, so the
	   laptop sits beside the text column instead of underneath it */
	if (innerWidth > 900) {
		fwd.copy(wantLook).sub(wantPos);
		right.crossVectors(fwd, UP).normalize()
			.multiplyScalar(-0.10 * wantPos.distanceTo(wantLook));
		wantPos.add(right);
		wantLook.add(right);
	}

	/* lid opens across the first stage and stays open */
	const open = seg(p, 0.02, 1 / last);
	lid.rotation.x = -open * 1.9;
	M.screen.color.setRGB(0.02 + 0.05 * open, 0.03 + 0.16 * open, 0.03 + 0.14 * open);

	/* the deck lifts away and dissolves as we move into stage 2 */
	const off = seg(p, 1.25 / last, 2 / last);
	deck.position.y = off * 14;
	M.deck.opacity = 1 - off;
	M.key.opacity = 1 - off;
	deck.visible = off < 0.995;

	/* the cooler sits on top of the two chips, so it comes off before there is
	   anything to look at — the order you take one apart in */
	const lift = seg(p, 2.3 / last, 3 / last);
	const ex = seg(p, 6.4 / last, 1);
	coolGrp.position.y = lift * COOL_LIFT + ex * 2.5;
	ramGrp.position.y = RAM.y + ex * 3.4;
	batteryGrp.position.y = -ex * 4.5;

	const active = Math.round(f);
	highlight(STAGES[active]);

	/* both of these are DOM writes, so only make them when they would change
	   something a person could see */
	const pct = Math.round(p * 1000) / 10;
	if (pct !== shownPct) {
		shownPct = pct;
		bar.style.width = pct + '%';
	}
	if (active !== shown) {
		shown = active;
		chapters.forEach((b, k) => b.classList.toggle('on', k === active));
	}
}

/* No emissive glow on the focused part: the materials are shared between
   components, so tinting one would light up every other part using it. The
   camera arriving at it and the pin naming it is the highlight. */
let pinned = null;
function highlight(stage) {
	if (pinned !== stage.part) {
		pinned = stage.part;
		pin.querySelector('span').textContent = stage.label;
	}
	pin.hidden = !stage.part;
	pin.classList.toggle('on', !!stage.part);
}

const projected = new THREE.Vector3();
function movePin() {
	if (!pinned) return;
	pinned.getWorldPosition(projected);
	projected.project(camera);
	if (projected.z > 1) { pin.classList.remove('on'); return; }
	/* transform rather than left/top: this runs every frame, and left/top
	   would put the browser through layout each time */
	pin.style.transform = 'translate3d(' +
		Math.round((projected.x * 0.5 + 0.5) * innerWidth) + 'px,' +
		Math.round((-projected.y * 0.5 + 0.5) * innerHeight) + 'px,0) translate(-50%,-50%)';
}

/* --------------------------------------------------------------- post chain */

/* Multisampling on the composer's own target, rather than a separate
   antialiasing pass at the end. The edges here are geometry, which is exactly
   what MSAA is for, and it costs one resolve instead of three full-screen
   passes and two lookup textures. */
const composer = new EffectComposer(renderer, new THREE.WebGLRenderTarget(
	innerWidth, innerHeight,
	{ type: THREE.HalfFloatType, samples: 4 }
));
composer.setPixelRatio(dpr);
composer.addPass(new RenderPass(scene, camera));

/* only the specular hits should bloom, not the whole frame */
const bloom = new UnrealBloomPass(new THREE.Vector2(innerWidth, innerHeight), 0.3, 0.55, 0.93);
composer.addPass(bloom);
composer.addPass(new OutputPass());

function setScale(next) {
	const clamped = Math.max(DPR_MIN, Math.min(DPR_MAX, next));
	if (Math.abs(clamped - dpr) < 0.02) return;
	dpr = clamped;
	renderer.setPixelRatio(dpr);
	composer.setPixelRatio(dpr);
}

/* ------------------------------------------------------------------- input */

addEventListener('scroll', () => { progress = readScroll(); }, { passive: true });

function goTo(i, behavior) {
	if (!anchors.length) measure();
	scrollTo({ top: anchors[i] - innerHeight / 2, behavior: behavior || 'auto' });
}

chapters.forEach((b, i) => b.addEventListener('click', () => {
	location.hash = STAGES[i].name;
	goTo(i, reduced ? 'auto' : 'smooth');
}));

function fromHash(behavior) {
	const i = STAGES.findIndex((s) => s.name === location.hash.slice(1));
	if (i >= 0) goTo(i, behavior);
	return i >= 0;
}
addEventListener('hashchange', () => fromHash(reduced ? 'auto' : 'smooth'));

const io = new IntersectionObserver(
	(entries) => entries.forEach((e) => e.target.classList.toggle('in', e.isIntersecting)),
	{ threshold: 0.3 }
);
document.querySelectorAll('.panel').forEach((p) => io.observe(p));

addEventListener('resize', () => {
	camera.aspect = innerWidth / innerHeight;
	camera.updateProjectionMatrix();
	renderer.setSize(innerWidth, innerHeight);
	composer.setSize(innerWidth, innerHeight);   /* passes get sized from this */
	measure();
	progress = readScroll();
	renderer.shadowMap.needsUpdate = true;
});

/* -------------------------------------------------------------------- loop */

const clock = new THREE.Clock();
let smoothed = 0;

/* --- resolution controller ------------------------------------------------
   There is no way to ask a browser what refresh rate it is running at, and
   requestAnimationFrame is capped to it anyway — so a fixed 120 fps target
   would just ratchet the resolution down forever on a 60 Hz screen. Instead
   the loop learns the display's own frame period by watching for the fastest
   frames it ever manages, and then treats "late relative to that" as the
   signal to drop resolution. On a 144 Hz panel it defends 144; on 60 Hz it
   defends 60, and spends the headroom on sharpness instead. */
let period = 1 / 60;          /* best frame time seen; converges to the vsync period */
let acc = 0, frames = 0, warmup = 0;

function adapt(dt) {
	warmup += dt;
	if (warmup < 1.2) return;                 /* ignore shader-compile spikes */
	if (dt > 1 / 500) period = Math.min(period, dt);

	acc += dt;
	frames++;
	if (acc < 0.4) return;
	const avg = acc / frames;
	acc = 0;
	frames = 0;

	if (avg > period * 1.35) setScale(dpr - 0.15);        /* missing frames */
	else if (avg < period * 1.08) setScale(dpr + 0.08);   /* room to spare */
}

/* the shadow map only needs redrawing when something in it moved */
let shadowAt = -1;
let idleTurn = 0;

function frame() {
	const dt = Math.min(clock.getDelta(), 0.05);

	/* the scroll position is followed, not obeyed: a little lag is the whole
	   difference between "3D on a page" and something that feels built */
	smoothed += (progress - smoothed) * (reduced ? 1 : 1 - Math.pow(0.0018, dt));
	apply(smoothed);

	camPos.lerp(wantPos, reduced ? 1 : 1 - Math.pow(0.0022, dt));
	camLook.lerp(wantLook, reduced ? 1 : 1 - Math.pow(0.0022, dt));
	camera.position.copy(camPos);
	camera.lookAt(camLook);

	/* a slow idle turn while the laptop is still closed */
	const turn = (1 - clamp01(smoothed * 7)) * Math.sin(clock.elapsedTime * 0.22) * 0.13;
	laptop.rotation.y = turn;

	coolGrp.traverse((o) => {
		if (o.userData.rotor) o.userData.rotor.rotation.y += dt * 7 * o.userData.dir;
	});

	if (Math.abs(smoothed - shadowAt) > 0.0004 || Math.abs(turn - idleTurn) > 0.0008) {
		shadowAt = smoothed;
		idleTurn = turn;
		renderer.shadowMap.needsUpdate = true;
	}

	movePin();
	composer.render();
	adapt(dt);
	requestAnimationFrame(frame);
}

measure();
addEventListener('load', measure);   /* fonts can change the panel heights */
fromHash('auto');
progress = smoothed = readScroll();
apply(progress);
camPos.copy(wantPos);
camLook.copy(wantLook);
camera.position.copy(camPos);
camera.lookAt(camLook);
frame();
