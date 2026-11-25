/**
 * Small UI sounds made with the Web Audio API (no audio files):
 *  - playPiano(step): a soft piano-like note, one per menu item
 *  - playClick(): a short tick for button presses
 * Sounds can be switched off; the choice is remembered on the device.
 */

const STORAGE_KEY = 'pm_ui_sound';

// C major pentatonic, so any hover order sounds pleasant.
const SCALE = [523.25, 587.33, 659.25, 783.99, 880.0, 1046.5, 1174.66, 1318.51];

let ctx = null;
let lastNoteAt = 0;

export function isSoundOn() {
  try {
    return localStorage.getItem(STORAGE_KEY) !== '0';
  } catch {
    return true;
  }
}

export function setSoundOn(on) {
  try {
    localStorage.setItem(STORAGE_KEY, on ? '1' : '0');
  } catch {
    /* storage blocked */
  }
}

export function getAudioContext() {
  return getContext();
}

function getContext() {
  const AC = window.AudioContext || window.webkitAudioContext;
  if (!AC) return null;
  if (!ctx) ctx = new AC();
  if (ctx.state === 'suspended') ctx.resume().catch(() => {});
  return ctx;
}

/** One piano-like note: a sine with quickly fading overtones. */
function note(audio, freq, volume = 0.16) {
  const t = audio.currentTime;
  const master = audio.createGain();
  master.gain.setValueAtTime(0.0001, t);
  master.gain.exponentialRampToValueAtTime(volume, t + 0.01);
  master.gain.exponentialRampToValueAtTime(0.0001, t + 1.2);
  master.connect(audio.destination);

  // [harmonic, loudness]; higher harmonics die faster, like a struck string
  [
    [1, 1],
    [2, 0.42],
    [3, 0.16],
    [4, 0.07],
  ].forEach(([harmonic, level]) => {
    const osc = audio.createOscillator();
    osc.type = 'sine';
    osc.frequency.value = freq * harmonic;
    const gain = audio.createGain();
    gain.gain.setValueAtTime(level, t);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + 1.2 / harmonic);
    osc.connect(gain);
    gain.connect(master);
    osc.start(t);
    osc.stop(t + 1.25);
  });
}

/** Piano note for menu item number `step` (wraps around the scale). */
export function playPiano(step = 0, volume = 0.16) {
  if (!isSoundOn()) return;
  const now = performance.now();
  if (now - lastNoteAt < 55) return; // avoid a machine-gun when sweeping fast
  lastNoteAt = now;
  const audio = getContext();
  if (!audio) return;
  note(audio, SCALE[((step % SCALE.length) + SCALE.length) % SCALE.length], volume);
}

/** Short, soft button "tick". */
export function playClick() {
  if (!isSoundOn()) return;
  const audio = getContext();
  if (!audio) return;
  const t = audio.currentTime;

  const osc = audio.createOscillator();
  osc.type = 'triangle';
  osc.frequency.setValueAtTime(980, t);
  osc.frequency.exponentialRampToValueAtTime(420, t + 0.09);

  const gain = audio.createGain();
  gain.gain.setValueAtTime(0.0001, t);
  gain.gain.exponentialRampToValueAtTime(0.2, t + 0.006);
  gain.gain.exponentialRampToValueAtTime(0.0001, t + 0.11);

  osc.connect(gain);
  gain.connect(audio.destination);
  osc.start(t);
  osc.stop(t + 0.13);
}

// ---------- Site-wide click sound ----------

const CLICKABLE =
  'button, a[href], [role="button"], [role="tab"], [role="radio"], summary';
let clickSoundInstalled = false;
let lastClickAt = 0;

/**
 * Plays the click tick for every button / link on the site. Called once at
 * startup. Add data-no-sound to an element (or a parent) to opt out.
 */
export function installClickSound() {
  if (clickSoundInstalled) return;
  clickSoundInstalled = true;

  document.addEventListener(
    'click',
    (e) => {
      const el = e.target.closest?.(CLICKABLE);
      if (!el || el.closest('[data-no-sound]')) return;
      if (el.disabled || el.getAttribute('aria-disabled') === 'true') return;

      const now = performance.now();
      if (now - lastClickAt < 60) return; // one tick per press
      lastClickAt = now;
      playClick();
    },
    true
  );
}

/** Play a little tune: scale steps (0-7), one per `gap` ms. */
export function playTune(steps, gap = 150, volume = 0.14) {
  if (!isSoundOn()) return;
  steps.forEach((step, i) => {
    setTimeout(() => {
      lastNoteAt = 0; // a tune may play faster than hover notes
      playPiano(step, volume);
    }, i * gap);
  });
}

/** Soft paper "swish" for turning a page. */
export function playPageFlip() {
  if (!isSoundOn()) return;
  const audio = getContext();
  if (!audio) return;
  const t = audio.currentTime;
  const dur = 0.22;

  const buffer = audio.createBuffer(1, Math.floor(audio.sampleRate * dur), audio.sampleRate);
  const data = buffer.getChannelData(0);
  for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;

  const src = audio.createBufferSource();
  src.buffer = buffer;

  const filter = audio.createBiquadFilter();
  filter.type = 'bandpass';
  filter.Q.value = 0.9;
  filter.frequency.setValueAtTime(3200, t);
  filter.frequency.exponentialRampToValueAtTime(1300, t + dur);

  const gain = audio.createGain();
  gain.gain.setValueAtTime(0.0001, t);
  gain.gain.exponentialRampToValueAtTime(0.22, t + 0.03);
  gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

  src.connect(filter);
  filter.connect(gain);
  gain.connect(audio.destination);
  src.start(t);
}

// ---------- Soft notes when hovering product / course cards ----------

const HOVER_CARDS = '.pm-course-card, .lc-card';
let hoverNotesInstalled = false;

/**
 * Hovering a card (mouse only) plays a quiet piano note; moving across a row
 * of cards plays a little melody. Touch screens stay silent while scrolling.
 */
export function installHoverNotes() {
  // Piano on hover is turned off: only the click sound plays now.
  // Remove the next line to bring the hover piano back.
  return;
  // eslint-disable-next-line no-unreachable
  if (hoverNotesInstalled) return;
  hoverNotesInstalled = true;

  let current = null;
  document.addEventListener(
    'pointerover',
    (e) => {
      if (e.pointerType !== 'mouse') return;
      const card = e.target.closest?.(HOVER_CARDS);
      if (!card) {
        current = null;
        return;
      }
      if (card === current) return;
      current = card;
      const index = card.parentElement
        ? Array.prototype.indexOf.call(card.parentElement.children, card)
        : 0;
      playPiano(index, 0.075);
    },
    true
  );
}

/** Tiny "squish" for squashing an ant: a soft thud plus a wet crunch. */
export function playSquish() {
  if (!isSoundOn()) return;
  const audio = getContext();
  if (!audio) return;
  const t = audio.currentTime;

  // low thud
  const osc = audio.createOscillator();
  osc.type = 'sine';
  osc.frequency.setValueAtTime(150, t);
  osc.frequency.exponentialRampToValueAtTime(55, t + 0.12);
  const thud = audio.createGain();
  thud.gain.setValueAtTime(0.0001, t);
  thud.gain.exponentialRampToValueAtTime(0.3, t + 0.008);
  thud.gain.exponentialRampToValueAtTime(0.0001, t + 0.14);
  osc.connect(thud);
  thud.connect(audio.destination);
  osc.start(t);
  osc.stop(t + 0.16);

  // short crunch of noise
  const dur = 0.1;
  const buffer = audio.createBuffer(1, Math.floor(audio.sampleRate * dur), audio.sampleRate);
  const data = buffer.getChannelData(0);
  for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
  const src = audio.createBufferSource();
  src.buffer = buffer;
  const filter = audio.createBiquadFilter();
  filter.type = 'lowpass';
  filter.frequency.setValueAtTime(1800, t);
  filter.frequency.exponentialRampToValueAtTime(300, t + dur);
  const crunch = audio.createGain();
  crunch.gain.setValueAtTime(0.18, t);
  crunch.gain.exponentialRampToValueAtTime(0.0001, t + dur);
  src.connect(filter);
  filter.connect(crunch);
  crunch.connect(audio.destination);
  src.start(t);
}

/** Two quick little crunches: the ant biting something. */
export function playBite() {
  if (!isSoundOn()) return;
  const audio = getContext();
  if (!audio) return;
  [0, 0.11].forEach((delay) => {
    const t = audio.currentTime + delay;
    const dur = 0.07;
    const buffer = audio.createBuffer(1, Math.floor(audio.sampleRate * dur), audio.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
    const src = audio.createBufferSource();
    src.buffer = buffer;
    const filter = audio.createBiquadFilter();
    filter.type = 'bandpass';
    filter.Q.value = 1.6;
    filter.frequency.setValueAtTime(2600, t);
    filter.frequency.exponentialRampToValueAtTime(700, t + dur);
    const gain = audio.createGain();
    gain.gain.setValueAtTime(0.22, t);
    gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);
    src.connect(filter);
    filter.connect(gain);
    gain.connect(audio.destination);
    src.start(t);
  });
}

/** Quick wing-flutter whoosh: the butterfly taking off in a hurry. */
export function playFlutter() {
  if (!isSoundOn()) return;
  const audio = getContext();
  if (!audio) return;
  const t = audio.currentTime;
  const dur = 0.32;
  const buffer = audio.createBuffer(1, Math.floor(audio.sampleRate * dur), audio.sampleRate);
  const data = buffer.getChannelData(0);
  for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;
  const src = audio.createBufferSource();
  src.buffer = buffer;
  const filter = audio.createBiquadFilter();
  filter.type = 'bandpass';
  filter.Q.value = 1.1;
  filter.frequency.setValueAtTime(2200, t);
  filter.frequency.exponentialRampToValueAtTime(3400, t + dur);
  // fast tremolo = the flapping
  const flap = audio.createGain();
  flap.gain.value = 0.5;
  const lfo = audio.createOscillator();
  lfo.frequency.value = 26;
  const lfoDepth = audio.createGain();
  lfoDepth.gain.value = 0.5;
  lfo.connect(lfoDepth);
  lfoDepth.connect(flap.gain);
  const env = audio.createGain();
  env.gain.setValueAtTime(0.0001, t);
  env.gain.exponentialRampToValueAtTime(0.13, t + 0.03);
  env.gain.exponentialRampToValueAtTime(0.0001, t + dur);
  src.connect(filter);
  filter.connect(flap);
  flap.connect(env);
  env.connect(audio.destination);
  src.start(t);
  lfo.start(t);
  lfo.stop(t + dur);
}
