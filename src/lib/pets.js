/**
 * Shared state between the two little pets (Ant.jsx and Butterfly.jsx), so the ant
 * can sometimes chase the butterfly. Nothing here draws anything.
 *
 *  - pets.butterfly : { x, y, resting, mode, dead } or null (updated by the butterfly every frame)
 *  - pets.ant       : { x, y, hunting } or null            (updated by the ant every frame)
 *  - petBus events  : 'butterfly-caught' (the ant bit it), 'butterfly-escape' (it got away),
 *                     'ant-start-hunt' (asks the ant to start a chase now: handy for testing)
 */

export const pets = {
  butterfly: null,
  ant: null,
  startedAt: performance.now(), // when the page opened
  lastButterflyTouch: 0, // last time the user tapped the butterfly or chased it with the mouse
  huntCooldownUntil: 0, // no new chase before this time
};

export const petBus = new EventTarget();

/** Called whenever the user plays with the butterfly (tap, or the mouse scares it). */
export const touchButterfly = () => {
  pets.lastButterflyTouch = performance.now();
};

/** True while someone is busy with the butterfly: then chases and new ants are rarer. */
export const playingWithButterfly = (ms = 25000) =>
  pets.lastButterflyTouch > 0 && performance.now() - pets.lastButterflyTouch < ms;
