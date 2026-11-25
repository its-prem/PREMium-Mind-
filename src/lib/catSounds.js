/**
 * Cat voices made with the Web Audio API (no audio files):
 * a normal meow, an angry growl and a sleepy yawn.
 * They follow the site-wide sound on/off switch.
 */
import { getAudioContext, isSoundOn } from './uiSounds';

/** One synthesized "meow-like" voice with adjustable shape. */
function voice({
  start,
  peak,
  end,
  dur,
  peakAt = 0.2,
  vol = 0.7,
  attack = 0.06,
  vibRate = 7,
  vibDepth = 14,
  formants = [750, 2100, 950],
  q = 3.5,
}) {
  const ctx = getAudioContext();
  if (!ctx) return;
  const t = ctx.currentTime;

  const osc = ctx.createOscillator();
  osc.type = 'sawtooth';
  osc.frequency.setValueAtTime(start, t);
  osc.frequency.linearRampToValueAtTime(peak, t + peakAt * dur);
  osc.frequency.exponentialRampToValueAtTime(end, t + dur);

  const vibrato = ctx.createOscillator();
  const vibratoGain = ctx.createGain();
  vibrato.frequency.value = vibRate;
  vibratoGain.gain.value = vibDepth;
  vibrato.connect(vibratoGain);
  vibratoGain.connect(osc.frequency);

  const filter = ctx.createBiquadFilter();
  filter.type = 'bandpass';
  filter.Q.value = q;
  filter.frequency.setValueAtTime(formants[0], t);
  filter.frequency.exponentialRampToValueAtTime(formants[1], t + peakAt * dur);
  filter.frequency.exponentialRampToValueAtTime(formants[2], t + dur);

  const gain = ctx.createGain();
  gain.gain.setValueAtTime(0.0001, t);
  gain.gain.exponentialRampToValueAtTime(vol, t + attack);
  gain.gain.setValueAtTime(vol, t + dur * 0.4);
  gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

  osc.connect(filter);
  filter.connect(gain);
  gain.connect(ctx.destination);

  osc.start(t);
  vibrato.start(t);
  osc.stop(t + dur + 0.05);
  vibrato.stop(t + dur + 0.05);
}

/** A short burst of noise (the "hiss" before a growl). */
function hiss(dur = 0.3, vol = 0.12) {
  const ctx = getAudioContext();
  if (!ctx) return;
  const t = ctx.currentTime;
  const buffer = ctx.createBuffer(1, Math.floor(ctx.sampleRate * dur), ctx.sampleRate);
  const data = buffer.getChannelData(0);
  for (let i = 0; i < data.length; i += 1) data[i] = Math.random() * 2 - 1;

  const src = ctx.createBufferSource();
  src.buffer = buffer;
  const filter = ctx.createBiquadFilter();
  filter.type = 'highpass';
  filter.frequency.value = 3500;
  const gain = ctx.createGain();
  gain.gain.setValueAtTime(vol, t);
  gain.gain.exponentialRampToValueAtTime(0.0001, t + dur);

  src.connect(filter);
  filter.connect(gain);
  gain.connect(ctx.destination);
  src.start(t);
}

/** Friendly meow (slightly different pitch each time). */
export function playMeow() {
  if (!isSoundOn()) return;
  const pitch = 0.92 + Math.random() * 0.2;
  voice({
    start: 430 * pitch,
    peak: 820 * pitch,
    end: 480 * pitch,
    dur: 0.75,
  });
}

/** Angry growl with a hiss: low, rough and a bit longer. */
export function playAngryMeow() {
  if (!isSoundOn()) return;
  hiss(0.28);
  voice({
    start: 230,
    peak: 340,
    end: 130,
    dur: 1.0,
    peakAt: 0.3,
    vol: 0.6,
    vibRate: 19,
    vibDepth: 34,
    formants: [520, 1100, 600],
    q: 2.4,
  });
}

/** Slow, sleepy yawn-meow when the cat wakes up. */
export function playYawn() {
  if (!isSoundOn()) return;
  voice({
    start: 300,
    peak: 560,
    end: 250,
    dur: 1.15,
    peakAt: 0.45,
    vol: 0.5,
    attack: 0.18,
    vibRate: 5,
    vibDepth: 10,
    formants: [600, 1500, 700],
    q: 2.8,
  });
}

/**
 * A soft purr that keeps going until the returned function is called.
 * (A low hum whose volume pulses ~24 times a second.)
 */
export function startPurr() {
  if (!isSoundOn()) return () => {};
  const ctx = getAudioContext();
  if (!ctx) return () => {};
  const t = ctx.currentTime;

  const master = ctx.createGain();
  master.gain.setValueAtTime(0.0001, t);
  master.gain.exponentialRampToValueAtTime(0.09, t + 0.25);
  master.connect(ctx.destination);

  // the pulsing: an LFO added on top of the master volume
  const lfo = ctx.createOscillator();
  lfo.frequency.value = 24;
  const lfoDepth = ctx.createGain();
  lfoDepth.gain.value = 0.07;
  lfo.connect(lfoDepth);
  lfoDepth.connect(master.gain);

  const tone = ctx.createOscillator();
  tone.type = 'sawtooth';
  tone.frequency.value = 62;
  const tone2 = ctx.createOscillator();
  tone2.type = 'triangle';
  tone2.frequency.value = 124;
  const filter = ctx.createBiquadFilter();
  filter.type = 'lowpass';
  filter.frequency.value = 420;
  tone.connect(filter);
  tone2.connect(filter);
  filter.connect(master);

  lfo.start(t);
  tone.start(t);
  tone2.start(t);

  let stopped = false;
  return () => {
    if (stopped) return;
    stopped = true;
    const now = ctx.currentTime;
    master.gain.cancelScheduledValues(now);
    master.gain.setValueAtTime(Math.max(master.gain.value, 0.0001), now);
    master.gain.exponentialRampToValueAtTime(0.0001, now + 0.3);
    [lfo, tone, tone2].forEach((o) => o.stop(now + 0.35));
  };
}
