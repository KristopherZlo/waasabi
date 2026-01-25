// scribble-avatar.ts
// Детерминированный генератор SVG-аватарок "каракуль-клубочек" по seed/нику.
// Зависимости: нет.

export type AvatarParams = {
  // Геометрия
  width: number;   // 512
  height: number;  // 512
  points: number;  // длина линии
  step: number;
  jitterXY: number;
  curvature: number;
  inertia: number;
  centerPull: number;
  margin: number;
  turnEvery: number;
  turnJitter: number;

  // Рендер
  lineWidth: number;
  alpha: number;
  smooth: number;
  roundCaps: boolean;
  invert: boolean;

  // SVG noise (не обязателен)
  paperNoise: number; // 0..35
};

export type AvatarResult = {
  seed: number;
  params: AvatarParams;
  svg: string;
  dataUrl: string;
};

type Rng = () => number;

const clamp = (n: number, a: number, b: number) => Math.min(b, Math.max(a, n));
const lerp = (a: number, b: number, t: number) => a + (b - a) * t;

function mulberry32(seed: number): Rng {
  let t = seed >>> 0;
  return function () {
    t += 0x6d2b79f5;
    let x = Math.imul(t ^ (t >>> 15), 1 | t);
    x ^= x + Math.imul(x ^ (x >>> 7), 61 | x);
    return ((x ^ (x >>> 14)) >>> 0) / 4294967296;
  };
}

// Нормальный стабильный хэш строки -> seed (32-bit)
// (cyrb128 -> берём первый компонент)
function cyrb128(str: string): [number, number, number, number] {
  let h1 = 1779033703, h2 = 3144134277, h3 = 1013904242, h4 = 2773480762;
  for (let i = 0; i < str.length; i++) {
    const k = str.charCodeAt(i);
    h1 = h2 ^ Math.imul(h1 ^ k, 597399067);
    h2 = h3 ^ Math.imul(h2 ^ k, 2869860233);
    h3 = h4 ^ Math.imul(h3 ^ k, 951274213);
    h4 = h1 ^ Math.imul(h4 ^ k, 2716044179);
  }
  h1 = Math.imul(h3 ^ (h1 >>> 18), 597399067);
  h2 = Math.imul(h4 ^ (h2 >>> 22), 2869860233);
  h3 = Math.imul(h1 ^ (h3 >>> 17), 951274213);
  h4 = Math.imul(h2 ^ (h4 >>> 19), 2716044179);
  return [(h1 ^ h2 ^ h3 ^ h4) >>> 0, h2 >>> 0, h3 >>> 0, h4 >>> 0];
}

export function seedFromName(name: string): number {
  return cyrb128(name.trim().toLowerCase())[0] >>> 0;
}

// Перевод "процент по ползунку" -> значение в диапазоне
function fromPercent(min: number, max: number, percent0to100: number): number {
  const t = clamp(percent0to100 / 100, 0, 1);
  return lerp(min, max, t);
}

// Детерминированное число в процентном диапазоне [a..b]
function rangedPercent(rng: Rng, a: number, b: number): number {
  return lerp(a, b, rng());
}

// Основные диапазоны (как в твоём UI)
const RANGES = {
  // points
  points: { min: 500, max: 12000 },

  // step
  step: { min: 0.3, max: 6.0 },

  // jitterXY
  jitterXY: { min: 0.0, max: 2.5 },

  // curvature
  curvature: { min: 0.0, max: 0.35 },

  // inertia
  inertia: { min: 0.2, max: 0.97 },

  // centerPull
  centerPull: { min: 0.0, max: 0.02 },

  // margin
  margin: { min: 0, max: 240 },

  // lineWidth
  lineWidth: { min: 0.4, max: 14.0 },

  // alpha
  alpha: { min: 0.1, max: 1.0 },

  // smooth
  smooth: { min: 0.0, max: 1.0 },

  // paperNoise
  paperNoise: { min: 0, max: 35 },
};

// Твои дефолтные правила (зависят от seed)
export function paramsFromSeed(seed: number): AvatarParams {
  const rng = mulberry32(seed);

  // Твои требования (проценты):
  // length 35–60%  -> points
  const lengthPct = rangedPercent(rng, 35, 50);
  const points = Math.round(fromPercent(RANGES.points.min, RANGES.points.max, lengthPct));

  // step 65% фикс
  const step = fromPercent(RANGES.step.min, RANGES.step.max, 65);

  // jitter 0–100% -> jitterXY
  const jitterPct = rangedPercent(rng, 0, 0);
  const jitterXY = fromPercent(RANGES.jitterXY.min, RANGES.jitterXY.max, jitterPct);

  // curvature 13–20%
  const curvaturePct = rangedPercent(rng, 13, 20);
  const curvature = fromPercent(RANGES.curvature.min, RANGES.curvature.max, curvaturePct);

  // inertia 100% (макс диапазона)
  const inertia = RANGES.inertia.max;

  // center pull 55–70%
  const centerPullPct = rangedPercent(rng, 75, 100);
  const centerPull = fromPercent(RANGES.centerPull.min, RANGES.centerPull.max, centerPullPct);

  // margin 0
  const margin = 0;

  // line width 30%
  const lineWidth = fromPercent(RANGES.lineWidth.min, RANGES.lineWidth.max, 30);

  // alpha 100%
  const alpha = RANGES.alpha.max;

  // smooth 100%
  const smooth = RANGES.smooth.max;

  // Разрешение 512x512
  const width = 512;
  const height = 512;

  // Параметры, которые ты не перечислил, но они влияют на стиль.
  // Я сделал их "тихими": зависят от seed, но в узких диапазонах, чтобы не ломали вид.
  const turnEvery = Math.round(lerp(26, 58, rng()));       // редкость смены дуги
  const turnJitter = lerp(0.03, 0.12, rng());             // неровность дуги

  // Шум бумаги: по умолчанию 0 (можешь включить на сайте отдельно)
  const paperNoise = 0;

  // Визуальные флаги
  const roundCaps = true;
  const invert = false;

  return {
    width,
    height,
    points,
    step,
    jitterXY,
    curvature,
    inertia,
    centerPull,
    margin,
    turnEvery,
    turnJitter,
    lineWidth,
    alpha,
    smooth,
    roundCaps,
    invert,
    paperNoise,
  };
}

// Генерация точек "кругляшами"
function generatePoints(p: AvatarParams, seed: number): Array<{ x: number; y: number }> {
  const rng = mulberry32(seed);
  const cx = p.width / 2;
  const cy = p.height / 2;

  let x = cx;
  let y = cy;

  let ang = rng() * Math.PI * 2;

  let omegaTarget = (rng() < 0.5 ? -1 : 1) * p.curvature;
  let omega = omegaTarget;

  const pts: Array<{ x: number; y: number }> = new Array(p.points);
  pts[0] = { x, y };

  const te = Math.max(1, Math.round(p.turnEvery));
  const inert = clamp(p.inertia, 0, 0.99);

  for (let i = 1; i < p.points; i++) {
    if (i % te === 0) {
      const dir = rng() < 0.5 ? -1 : 1;
      const mag = p.curvature * (0.55 + rng() * 0.95);
      omegaTarget = dir * mag;
    }

    omega = omega * inert + omegaTarget * (1 - inert);
    ang += omega + (rng() - 0.5) * p.turnJitter;

    x += Math.cos(ang) * p.step + (rng() - 0.5) * p.jitterXY;
    y += Math.sin(ang) * p.step + (rng() - 0.5) * p.jitterXY;

    x += (cx - x) * p.centerPull;
    y += (cy - y) * p.centerPull;

    const left = p.margin;
    const right = p.width - p.margin;
    const top = p.margin;
    const bottom = p.height - p.margin;

    if (x < left) {
      x = left + (left - x) * 0.35;
      ang = Math.PI - ang;
      omegaTarget = Math.abs(omegaTarget);
    } else if (x > right) {
      x = right - (x - right) * 0.35;
      ang = Math.PI - ang;
      omegaTarget = -Math.abs(omegaTarget);
    }

    if (y < top) {
      y = top + (top - y) * 0.35;
      ang = -ang;
      omegaTarget = -omegaTarget;
    } else if (y > bottom) {
      y = bottom - (y - bottom) * 0.35;
      ang = -ang;
      omegaTarget = -omegaTarget;
    }

    pts[i] = { x, y };
  }

  return pts;
}

// Уменьшение количества точек для SVG (иначе будет гигантский path)
function decimate(pts: Array<{ x: number; y: number }>, target: number): Array<{ x: number; y: number }> {
  if (pts.length <= target) return pts;
  const step = pts.length / target;
  const out: Array<{ x: number; y: number }> = [];
  for (let i = 0; i < target; i++) {
    out.push(pts[Math.floor(i * step)]);
  }
  // гарантируем последнюю точку
  out[out.length - 1] = pts[pts.length - 1];
  return out;
}

function num(n: number): number {
  return Number(n.toFixed(2));
}

// Catmull-Rom -> Bezier path
function pointsToPathD(pts: Array<{ x: number; y: number }>, smooth: number): string {
  if (pts.length < 2) return "";
  const s = clamp(smooth, 0, 1);

  if (s <= 0) {
    let d = `M ${num(pts[0].x)} ${num(pts[0].y)}`;
    for (let i = 1; i < pts.length; i++) d += ` L ${num(pts[i].x)} ${num(pts[i].y)}`;
    return d;
  }

  let d = `M ${num(pts[0].x)} ${num(pts[0].y)}`;
  const k = s / 6;

  for (let i = 0; i < pts.length - 1; i++) {
    const p0 = pts[Math.max(0, i - 1)];
    const p1 = pts[i];
    const p2 = pts[i + 1];
    const p3 = pts[Math.min(pts.length - 1, i + 2)];

    const cp1x = p1.x + (p2.x - p0.x) * k;
    const cp1y = p1.y + (p2.y - p0.y) * k;
    const cp2x = p2.x - (p3.x - p1.x) * k;
    const cp2y = p2.y - (p3.y - p1.y) * k;

    d += ` C ${num(cp1x)} ${num(cp1y)} ${num(cp2x)} ${num(cp2y)} ${num(p2.x)} ${num(p2.y)}`;
  }

  return d;
}

function buildSVG(p: AvatarParams, pathD: string, seed: number): string {
  const bg = p.invert ? "#fff" : "#000";
  const stroke = p.invert ? "#000" : "#fff";

  const opacity = clamp(p.alpha, 0.05, 1);
  const cap = p.roundCaps ? "round" : "butt";
  const join = p.roundCaps ? "round" : "miter";

  const useNoise = p.paperNoise > 0;
  const noiseAlpha = clamp(p.paperNoise / 120, 0, 0.55);

  const defs = useNoise
    ? `<filter id="paperNoise" x="-20%" y="-20%" width="140%" height="140%">
  <feTurbulence type="fractalNoise" baseFrequency="0.8" numOctaves="2" seed="${seed}" result="n"/>
  <feColorMatrix type="matrix" values="0 0 0 0 1  0 0 0 0 1  0 0 0 0 1  0 0 0 ${noiseAlpha} 0" in="n" result="na"/>
  <feBlend mode="overlay" in="SourceGraphic" in2="na"/>
</filter>`
    : "";

  const filterAttr = useNoise ? ` filter="url(#paperNoise)"` : "";

  return `<?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" width="${p.width}" height="${p.height}" viewBox="0 0 ${p.width} ${p.height}">
${defs ? `  <defs>\n${defs}\n  </defs>` : ""}
  <rect width="100%" height="100%" fill="${bg}"/>
  <g${filterAttr}>
    <path d="${pathD}" fill="none" stroke="${stroke}" stroke-width="${p.lineWidth}" stroke-opacity="${opacity}"
      stroke-linecap="${cap}" stroke-linejoin="${join}"/>
  </g>
</svg>`;
}

function svgToDataUrl(svgText: string): string {
  const encoded = encodeURIComponent(svgText)
    .replace(/'/g, "%27")
    .replace(/\(/g, "%28")
    .replace(/\)/g, "%29");
  return `data:image/svg+xml;charset=utf-8,${encoded}`;
}

export type CreateAvatarOptions = {
  // Чтобы SVG не раздувался: сколько точек оставить в path.
  // 900–1400 обычно норм для аватарки.
  svgPointBudget?: number;

  // Можно переопределить параметры после paramsFromSeed
  override?: Partial<AvatarParams>;
};

export function createAvatarFromSeed(seed: number, opts: CreateAvatarOptions = {}): AvatarResult {
  const base = paramsFromSeed(seed);
  const params: AvatarParams = { ...base, ...(opts.override ?? {}) };

  const pts = generatePoints(params, seed);

  const budget = clamp(opts.svgPointBudget ?? 1200, 200, 4000);
  const pts2 = decimate(pts, budget);

  const pathD = pointsToPathD(pts2, params.smooth);
  const svg = buildSVG(params, pathD, seed);
  const dataUrl = svgToDataUrl(svg);

  return { seed, params, svg, dataUrl };
}

export function createAvatarFromName(name: string, opts: CreateAvatarOptions = {}): AvatarResult {
  return createAvatarFromSeed(seedFromName(name), opts);
}

const downloadBlob = (blob: Blob, filename: string): void => {
  const url = URL.createObjectURL(blob);
  const link = document.createElement("a");
  link.href = url;
  link.download = filename;
  link.rel = "noopener";
  link.click();
  window.setTimeout(() => URL.revokeObjectURL(url), 1000);
};

// Опционально: скачать SVG файлом
export function downloadAvatarSVG(res: AvatarResult, filename?: string): void {
  const blob = new Blob([res.svg], { type: "image/svg+xml;charset=utf-8" });
  downloadBlob(blob, filename ?? `avatar_${res.seed}.svg`);
}
