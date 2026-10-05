<?php
/**
 * D2D MCQ Test — chapter-wise online practice test (standalone, no DB, no login).
 * Upload to Hostinger premind/ as: d2d_mcq_test.php
 * Needs diplomawallah-logo.png in the same folder (header + result logo).
 *
 * Sab kuch isi file me hai. Naya test banane ke liye sirf niche wala
 * $TEST array badlo — baaki page khud adjust ho jata hai.
 *
 *   title              test ka naam (badge me dikhta hai)
 *   tags               chhote chips (Physics / D2D / Diploma)
 *   total_minutes      pura test kitne minute ka (overall countdown)
 *   per_question_sec   ek question ka time limit (0 = no per-question limit)
 *   mark               sahi answer ke marks
 *   negative           galat answer par kitna katega (0 = no negative)
 *   home_url           result page ke "Back to Home" button ka link
 *   questions          q = sawaal, o = 4 options, a = sahi option ka index (0-3),
 *                      e = explanation (Review Answers me dikhta hai)
 */

$TEST = [
    'title'            => 'Atomic Structure',
    'subtitle'         => 'Chapter-wise Practice Test',
    'tags'             => ['Physics', 'D2D', 'Diploma'],
    'total_minutes'    => 20,
    'per_question_sec' => 60,
    'mark'             => 1,
    'negative'         => 0.25,
    'home_url'         => 'https://diplomawallah.in/',

    'questions' => [
        ['q' => 'What is the atomic number of a carbon atom?',
         'o' => ['4', '6', '8', '12'], 'a' => 1,
         'e' => 'Carbon has 6 protons in its nucleus, so its atomic number Z = 6.'],

        ['q' => 'Which sub-atomic particle carries a negative charge?',
         'o' => ['Proton', 'Neutron', 'Electron', 'Nucleus'], 'a' => 2,
         'e' => 'The electron carries a charge of -1.6 x 10^-19 C. Protons are positive and neutrons are neutral.'],

        ['q' => 'The mass of a proton is approximately',
         'o' => ['9.11 x 10^-31 kg', '1.67 x 10^-27 kg', '1.6 x 10^-19 kg', '6.63 x 10^-34 kg'], 'a' => 1,
         'e' => 'A proton weighs about 1.67 x 10^-27 kg, roughly 1836 times the mass of an electron.'],

        ['q' => 'The electron was discovered by',
         'o' => ['Rutherford', 'J. J. Thomson', 'Chadwick', 'Niels Bohr'], 'a' => 1,
         'e' => 'J. J. Thomson discovered the electron in 1897 through his cathode ray tube experiments.'],

        ['q' => 'The neutron was discovered by',
         'o' => ['James Chadwick', 'J. J. Thomson', 'John Dalton', 'Henry Moseley'], 'a' => 0,
         'e' => 'James Chadwick discovered the neutron in 1932 and won the Nobel Prize for it in 1935.'],

        ['q' => "Rutherford's alpha particle scattering experiment proved the existence of the",
         'o' => ['Electron', 'Nucleus', 'Neutron', 'Isotope'], 'a' => 1,
         'e' => 'Most alpha particles passed straight through, but a few bounced back, proving a small dense positive nucleus.'],

        ['q' => 'The maximum number of electrons that the L shell can hold is',
         'o' => ['2', '8', '18', '32'], 'a' => 1,
         'e' => 'Capacity of a shell is 2n^2. For the L shell n = 2, so 2 x 2^2 = 8 electrons.'],

        ['q' => 'Isotopes of an element always have the same',
         'o' => ['Mass number', 'Number of neutrons', 'Atomic number', 'Number of nucleons'], 'a' => 2,
         'e' => 'Isotopes have the same number of protons (same Z) but a different number of neutrons.'],

        ['q' => 'The charge on one electron is',
         'o' => ['+1.6 x 10^-19 C', '-1.6 x 10^-19 C', '-9.1 x 10^-31 C', 'Zero'], 'a' => 1,
         'e' => 'An electron carries one unit of negative charge, -1.6 x 10^-19 coulomb.'],

        ['q' => "In Bohr's model the angular momentum of an electron is an integral multiple of",
         'o' => ['h', 'h / 2pi', '2pi h', 'h / pi'], 'a' => 1,
         'e' => 'Bohr quantisation condition: mvr = n h / 2pi, where n = 1, 2, 3 ...'],

        ['q' => 'The number of neutrons in a sodium atom (Z = 11, A = 23) is',
         'o' => ['11', '12', '23', '34'], 'a' => 1,
         'e' => 'Neutrons = A - Z = 23 - 11 = 12.'],

        ['q' => 'Which of the following is a pair of isobars?',
         'o' => ['C-14 and N-14', 'H-1 and H-2', 'Cl-35 and Cl-37', 'O-16 and O-18'], 'a' => 0,
         'e' => 'Isobars have the same mass number but different atomic numbers. C-14 and N-14 both have A = 14.'],

        ['q' => 'The energy of an electron in the nth orbit of a hydrogen atom is proportional to',
         'o' => ['n', 'n^2', '1 / n', '1 / n^2'], 'a' => 3,
         'e' => 'E(n) = -13.6 / n^2 eV, so the energy varies as 1 / n^2.'],

        ['q' => 'The radius of the first Bohr orbit of hydrogen is about',
         'o' => ['0.529 angstrom', '1.00 angstrom', '2.50 angstrom', '5.29 angstrom'], 'a' => 0,
         'e' => 'The Bohr radius a0 = 0.529 angstrom = 0.529 x 10^-10 m.'],

        ['q' => 'The shape of an orbital is decided by which quantum number?',
         'o' => ['Principal (n)', 'Azimuthal (l)', 'Magnetic (m)', 'Spin (s)'], 'a' => 1,
         'e' => 'The azimuthal quantum number l gives the sub-shell and hence the shape: l = 0 is s (spherical), l = 1 is p (dumb-bell).'],

        ['q' => 'The maximum number of electrons in a d sub-shell is',
         'o' => ['2', '6', '10', '14'], 'a' => 2,
         'e' => 'A d sub-shell has 5 orbitals and each orbital holds 2 electrons, so 10 in total.'],

        ['q' => "Pauli's exclusion principle states that no two electrons in an atom can have",
         'o' => ['The same spin', 'The same energy', 'All four quantum numbers the same', 'The same orbital'], 'a' => 2,
         'e' => 'Two electrons may share an orbital only if their spins differ, so all four quantum numbers can never match.'],

        ['q' => 'The electronic configuration of chlorine (Z = 17) is',
         'o' => ['2, 8, 7', '2, 8, 8', '2, 8, 6', '2, 7, 8'], 'a' => 0,
         'e' => 'K = 2, L = 8, M = 7. Chlorine needs one more electron to complete its octet.'],

        ['q' => 'An atom as a whole is electrically neutral because',
         'o' => ['It contains no charge', 'Protons equal electrons in number', 'It contains neutrons', 'The nucleus is positive'], 'a' => 1,
         'e' => 'Equal numbers of protons and electrons make the positive and negative charges cancel out.'],

        ['q' => 'The de Broglie wavelength of a moving particle is given by',
         'o' => ['lambda = h / p', 'lambda = p / h', 'lambda = h p', 'lambda = h / m c^2'], 'a' => 0,
         'e' => 'de Broglie relation: lambda = h / p = h / m v. Heavier or faster particles have a shorter wavelength.'],
    ],
];

$QN = count($TEST['questions']);
$CLIENT = [
    'title'    => $TEST['title'],
    'totalSec' => (int)$TEST['total_minutes'] * 60,
    'perQSec'  => (int)$TEST['per_question_sec'],
    'mark'     => (float)$TEST['mark'],
    'negative' => (float)$TEST['negative'],
    'count'    => $QN,
    'questions' => array_map(function ($x) {
        return ['q' => $x['q'], 'o' => $x['o'], 'a' => (int)$x['a'], 'e' => isset($x['e']) ? $x['e'] : ''];
    }, $TEST['questions']),
];
$totalMarks = $QN * $TEST['mark'];
$num = function ($v) { return rtrim(rtrim(number_format((float)$v, 2, '.', ''), '0'), '.'); };
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#5b1668">
<title>MCQ Test — <?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?> | Diploma Wallah</title>
<link rel="icon" href="diplomawallah-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    --purple: #4c1565;
    --purple-2: #6b1b7a;
    --crimson: #a81d45;
    --grad: linear-gradient(135deg, #4c1565 0%, #7a1a5e 55%, #a81d45 100%);
    --ink: #1f1430;
    --muted: #6b6480;
    --line: #ece7f3;
    --line-2: #d9d2e6;
    --bg: #f7f5fb;
    --card: #ffffff;
    --green: #16a34a;
    --green-bg: #e9f9ef;
    --red: #e11d48;
    --ease: cubic-bezier(.4, 0, .2, 1);
    --shadow: 0 6px 20px rgba(76, 21, 101, .08);
  }

  * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
  html { -webkit-text-size-adjust: 100%; }
  body {
    font-family: 'Poppins', system-ui, sans-serif;
    background: var(--bg); color: var(--ink); line-height: 1.55; min-height: 100dvh;
  }
  body::before {
    content: ""; position: fixed; inset: 0; z-index: -1; pointer-events: none;
    background:
      radial-gradient(60vw 40vw at 85% -5%, rgba(168, 29, 69, .10), transparent 65%),
      radial-gradient(55vw 40vw at -10% 8%, rgba(76, 21, 101, .10), transparent 65%);
  }
  /* test chal raha ho to page khud scroll na ho — sirf question area scroll karega */
  body.mode-test { overflow: hidden; height: 100dvh; }
  .wrap { width: 100%; max-width: 520px; margin: 0 auto; padding: 0 16px 34px; }
  button { font-family: inherit; cursor: pointer; border: none; background: none; color: inherit; }
  a { color: inherit; }
  .screen { display: none; }
  .screen.show { display: block; animation: fade .28s var(--ease); }
  @keyframes fade { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }

  .brand-logo { width: 100%; height: 100%; object-fit: contain; display: block; }
  .logo-fallback { width: 100%; height: 100%; border-radius: 50%; background: var(--grad); display: grid; place-items: center; color: #fff; font-size: .9em; }
  .btn-primary {
    display: flex; align-items: center; justify-content: center; gap: 10px; width: 100%;
    background: var(--grad); color: #fff; font-weight: 700; font-size: 1.02rem;
    padding: 16px 20px; border-radius: 14px; letter-spacing: .4px;
    box-shadow: 0 8px 20px rgba(118, 24, 78, .32); transition: transform .15s var(--ease);
  }
  .btn-primary:active { transform: scale(.985); }
  .btn-ghost {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    background: #fff; color: var(--ink); font-weight: 600; font-size: .92rem;
    padding: 13px 16px; border-radius: 12px; border: 1px solid var(--line-2); text-decoration: none;
  }
  .btn-ghost:active { background: #faf7fd; }
  .powered { text-align: center; color: var(--muted); font-size: .76rem; margin-top: 18px; display: flex; align-items: center; gap: 10px; justify-content: center; }
  .powered::before, .powered::after { content: ""; height: 1px; width: 26px; background: var(--line-2); }

  /* ============ START ============ */
  .start-head { text-align: center; padding: 26px 0 6px; }
  .start-logo { width: 122px; height: 122px; margin: 0 auto 10px; }
  .start-name { font-weight: 700; letter-spacing: 3px; font-size: .95rem; color: var(--purple); }
  .start-rule { width: 54px; height: 3px; border-radius: 3px; background: var(--grad); margin: 8px auto 16px; }
  .start-title { font-size: 2.5rem; font-weight: 800; letter-spacing: -.5px; line-height: 1.05; }
  .start-title span { color: var(--crimson); }

  .chip { background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); color: #fff; font-size: .7rem; font-weight: 600; padding: 3px 11px; border-radius: 99px; }
  .chip-row { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 7px; }
  .chapter-card { background: var(--grad); color: #fff; border-radius: 16px; padding: 16px 18px; margin-top: 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 10px 24px rgba(92, 22, 90, .28); }
  .chapter-card .ic { width: 42px; height: 42px; flex: none; opacity: .92; }
  .chapter-card .tx { min-width: 0; flex: 1; }
  .chapter-card h2 { font-size: 1.22rem; font-weight: 800; letter-spacing: .4px; line-height: 1.2; text-transform: uppercase; }
  .sub-line { display: flex; align-items: center; gap: 10px; justify-content: center; color: var(--muted); font-size: .85rem; margin: 14px 0 16px; }
  .sub-line::before, .sub-line::after { content: ""; height: 1px; width: 24px; background: var(--line-2); }

  .stat-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .stat-row { display: grid; grid-template-columns: 1fr 1fr; }
  .stat-row + .stat-row { border-top: 1px solid var(--line); }
  .stat { display: flex; align-items: center; gap: 11px; padding: 13px 14px; min-width: 0; }
  .stat + .stat { border-left: 1px solid var(--line); }
  .stat.full { grid-column: 1 / -1; }
  .stat .si { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; font-size: .95rem; background: #f3ecfa; color: var(--purple); }
  .stat.rose .si { background: #fdeef2; color: var(--crimson); }
  .stat .sl { font-size: .74rem; color: var(--muted); line-height: 1.3; }
  .stat .sv { font-size: 1.05rem; font-weight: 700; color: var(--purple); line-height: 1.25; }
  .stat.rose .sv { color: var(--crimson); }

  .rules { margin-top: 16px; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--line); }
  .rules-head { background: var(--grad); color: #fff; padding: 13px 16px; font-weight: 700; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; font-size: .95rem; }
  .rules ol { list-style: none; background: var(--card); padding: 6px 14px 12px; }
  .rules li { display: flex; gap: 12px; align-items: flex-start; padding: 9px 0; font-size: .87rem; color: #40374f; }
  .rules li + li { border-top: 1px solid var(--line); }
  .rules li .n { width: 24px; height: 24px; flex: none; border-radius: 50%; background: #f3ecfa; color: var(--purple); font-size: .74rem; font-weight: 700; display: grid; place-items: center; margin-top: 1px; }
  .rules li b { color: var(--crimson); font-weight: 700; }
  .resume-note { margin-top: 14px; background: #fff8ea; border: 1px solid #f6e2bb; color: #8a5a00; border-radius: 12px; padding: 11px 14px; font-size: .82rem; display: flex; gap: 10px; align-items: flex-start; }

  /* ============ TEST (fixed shell — only the question area moves) ============ */
  .test-shell { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
  .topbar { flex: none; background: var(--grad); color: #fff; box-shadow: 0 2px 14px rgba(76, 21, 101, .25); }
  .topbar-in { max-width: 520px; margin: 0 auto; padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
  .icon-btn { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; color: #fff; font-size: 1.05rem; background: rgba(255,255,255,.12); }
  .icon-btn:active { background: rgba(255,255,255,.22); }
  .topbar .bl { width: 36px; height: 36px; flex: none; border-radius: 50%; background: #fff; padding: 2px; overflow: hidden; }
  .topbar .tt { flex: 1; min-width: 0; }
  .topbar .tt b { display: block; font-size: .95rem; font-weight: 700; letter-spacing: .6px; line-height: 1.2; }
  .topbar .tt small { display: block; font-size: .6rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }

  .test-main { flex: 1 1 auto; min-height: 0; display: flex; justify-content: center; padding: 12px 16px 14px; }
  .qcard { width: 100%; max-width: 520px; display: flex; flex-direction: column; min-height: 0;
           background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .qfix { flex: none; padding: 13px 14px 0; }
  .qmid { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 14px; }
  .qbot { flex: none; padding: 10px 14px 13px; border-top: 1px solid var(--line); background: #fdfcfe; }

  .qtop { display: flex; align-items: center; gap: 10px; }
  .qtop .bk { width: 30px; height: 30px; flex: none; border-radius: 9px; background: #f3ecfa; color: var(--purple); display: grid; place-items: center; }
  .qtop .ch { flex: 1; min-width: 0; font-weight: 700; font-size: .98rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .qtop .cnt { font-weight: 700; color: var(--purple); font-size: .95rem; flex: none; font-variant-numeric: tabular-nums; }
  .qtop .cnt span { color: var(--muted); font-weight: 500; }

  .timers { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 11px; }
  .timer { display: flex; align-items: center; gap: 10px; border-radius: 13px; padding: 9px 11px; border: 1px solid var(--line); background: #faf8fd; }
  .timer .ti { width: 32px; height: 32px; flex: none; border-radius: 50%; display: grid; place-items: center; font-size: .9rem; background: #f0e8f8; color: var(--purple); }
  .timer .tl { font-size: .68rem; color: var(--muted); line-height: 1.2; }
  .timer .tv { font-size: 1.1rem; font-weight: 700; color: var(--purple); font-variant-numeric: tabular-nums; letter-spacing: .5px; }
  .timer.q { background: #fff6f8; border-color: #f7dde4; }
  .timer.q .ti { background: #fde7ed; color: var(--crimson); }
  .timer.q .tv { color: var(--crimson); }
  .timer.warn { animation: pulse 1s infinite; }
  @keyframes pulse { 50% { opacity: .5; } }
  .timer.off { opacity: .45; }

  .qhead { display: flex; align-items: center; justify-content: space-between; gap: 10px; margin: 12px 0 2px; }
  .qnum { background: var(--grad); color: #fff; font-weight: 700; font-size: .86rem; padding: 8px 16px; border-radius: 10px; letter-spacing: .3px; }
  .bookmark { display: flex; align-items: center; gap: 7px; font-size: .84rem; font-weight: 600; color: var(--muted); padding: 7px 10px; border-radius: 9px; }
  .bookmark.on { color: var(--crimson); background: #fdeef2; }

  .qtext { font-size: 1.02rem; font-weight: 600; line-height: 1.5; margin: 12px 0 2px; }
  .timeover { display: inline-flex; align-items: center; gap: 7px; background: #fdecf1; color: var(--red); font-size: .76rem; font-weight: 600; padding: 5px 11px; border-radius: 99px; margin-top: 8px; }
  .opts { display: flex; flex-direction: column; gap: 9px; margin: 12px 0 14px; }
  .opt { display: flex; align-items: center; gap: 13px; width: 100%; text-align: left; background: #fff; border: 1.5px solid var(--line-2); border-radius: 13px; padding: 12px 13px; transition: border-color .15s var(--ease), background .15s var(--ease); }
  .opt .k { width: 33px; height: 33px; flex: none; border-radius: 10px; background: #f3f1f7; color: #5b5370; font-weight: 700; display: grid; place-items: center; font-size: .88rem; }
  .opt .v { flex: 1; min-width: 0; font-size: .94rem; font-weight: 500; overflow-wrap: anywhere; }
  .opt.sel { border-color: var(--purple-2); background: #f8f3fc; }
  .opt.sel .k { background: var(--grad); color: #fff; }
  .opt.sel .v { font-weight: 600; }
  .opt.dead { opacity: .62; pointer-events: none; }

  .act-row { display: grid; grid-template-columns: 1fr 1.25fr; gap: 9px; }
  .act-row.two { grid-template-columns: 1fr 1fr; margin-top: 9px; }
  .btn-sm { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 10px; border-radius: 12px; font-weight: 600; font-size: .89rem; border: 1px solid var(--line-2); background: #fff; color: var(--ink); }
  .btn-sm:active { background: #f7f4fb; }
  .btn-sm.grad { background: var(--grad); color: #fff; border-color: transparent; box-shadow: 0 5px 14px rgba(118, 24, 78, .26); }
  .btn-sm:disabled { opacity: .45; pointer-events: none; }

  .statusbar { display: flex; align-items: center; gap: 12px; margin-top: 11px; padding-top: 10px; border-top: 1px dashed var(--line-2); flex-wrap: wrap; }
  .statusbar span { display: flex; align-items: center; gap: 6px; font-size: .74rem; color: #50485f; }
  .statusbar span b { font-weight: 700; color: var(--ink); }
  .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
  .dot.ans { background: var(--green); }
  .dot.not { background: #fff; border: 1.5px solid var(--line-2); }
  .dot.mark { background: #f472b6; }
  .btn-submit { margin-left: auto; background: var(--grad); color: #fff; font-weight: 700; font-size: .8rem; letter-spacing: .5px; padding: 9px 18px; border-radius: 10px; display: flex; align-items: center; gap: 7px; box-shadow: 0 5px 14px rgba(118, 24, 78, .26); }

  /* ============ PALETTE SHEET ============ */
  .scrim { position: fixed; inset: 0; background: rgba(31, 20, 48, .55); backdrop-filter: blur(2px); z-index: 60; opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
  .scrim.show { opacity: 1; visibility: visible; }
  .sheet { position: fixed; left: 0; right: 0; top: 0; z-index: 70; transform: translateY(-104%); transition: transform .3s var(--ease); }
  .sheet.show { transform: none; }
  .sheet-in { max-width: 520px; margin: 0 auto; padding: 0 12px; }
  .sheet-box { background: #fff; border-radius: 0 0 20px 20px; box-shadow: 0 16px 40px rgba(31, 20, 48, .3); overflow: hidden; max-height: 92dvh; display: flex; flex-direction: column; }
  .sheet-bar { background: var(--grad); color: #fff; padding: 11px 14px; display: flex; align-items: center; gap: 12px; flex: none; }
  .sheet-bar .bl { width: 34px; height: 34px; border-radius: 50%; background: #fff; padding: 2px; flex: none; overflow: hidden; }
  .sheet-bar .tt { flex: 1; min-width: 0; }
  .sheet-bar .tt b { display: block; font-size: .92rem; font-weight: 700; letter-spacing: .6px; }
  .sheet-bar .tt small { font-size: .58rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }
  .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 14px 0; flex: none; }
  .tab { padding: 10px; border-radius: 10px; font-weight: 600; font-size: .86rem; background: #f4f1f8; color: var(--muted); }
  .tab.on { background: var(--grad); color: #fff; box-shadow: 0 5px 14px rgba(118, 24, 78, .24); }
  .pal-body { padding: 14px; overflow-y: auto; }
  .pal-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 9px; }
  .pal-btn { aspect-ratio: 1 / 1; border-radius: 10px; font-weight: 700; font-size: .92rem; border: 1.5px solid var(--line-2); background: #fff; color: #4c4459; display: grid; place-items: center; }
  .pal-btn.ans { background: #dcfce7; border-color: #86efac; color: #15803d; }
  .pal-btn.mark { background: #fce7f3; border-color: #f9a8d4; color: #be185d; }
  .pal-btn.cur { border-color: var(--purple-2); border-width: 2px; color: var(--purple); background: #f6efff; }
  .pal-note { display: flex; gap: 10px; align-items: flex-start; background: #f8f6fc; border: 1px solid var(--line); border-radius: 11px; padding: 11px 12px; margin-top: 14px; font-size: .78rem; color: var(--muted); line-height: 1.55; }
  .pal-note i { color: var(--purple-2); margin-top: 2px; }
  .tab-pane { display: none; }
  .tab-pane.show { display: block; }
  .ins-list { list-style: none; }
  .ins-list li { display: flex; gap: 10px; padding: 9px 0; font-size: .85rem; color: #40374f; }
  .ins-list li + li { border-top: 1px solid var(--line); }
  .ins-list i { color: var(--purple-2); margin-top: 3px; }

  /* ============ RESULT ============ */
  .res-hero { background: var(--grad); color: #fff; border-radius: 18px; padding: 26px 18px 22px; text-align: center; margin-top: 16px; box-shadow: 0 12px 30px rgba(92, 22, 90, .3); position: relative; overflow: hidden; }
  .res-hero::after { content: ""; position: absolute; inset: 0; background: radial-gradient(60% 50% at 50% 0%, rgba(255,255,255,.18), transparent 70%); }
  .res-hero .tro { font-size: 2.9rem; position: relative; }
  .res-hero h2 { font-size: 1.5rem; font-weight: 800; margin-top: 6px; position: relative; }
  .res-hero p { font-size: .83rem; opacity: .9; position: relative; }
  .res-hero p b { display: block; font-size: .96rem; margin-top: 2px; }
  .res-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 9px; margin-top: 14px; }
  .res-c { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 12px 8px; text-align: center; box-shadow: var(--shadow); }
  .res-c i { font-size: 1.1rem; }
  .res-c .lb { font-size: .74rem; color: var(--muted); margin-top: 3px; }
  .res-c .vl { font-size: 1.4rem; font-weight: 800; line-height: 1.2; }
  .res-c .mk { font-size: .72rem; font-weight: 600; }
  .res-c.ok i, .res-c.ok .vl, .res-c.ok .mk { color: var(--green); }
  .res-c.no i, .res-c.no .vl, .res-c.no .mk { color: var(--red); }
  .res-c.sk i, .res-c.sk .vl, .res-c.sk .mk { color: #94a3b8; }
  .score-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; margin-top: 12px; text-align: center; box-shadow: var(--shadow); }
  .score-card .md { font-size: 2rem; }
  .score-card .lb { font-size: .84rem; color: var(--muted); }
  .score-card .sc { font-size: 2.5rem; font-weight: 800; color: var(--crimson); line-height: 1.1; }
  .score-card .sc small { font-size: 1.1rem; color: var(--muted); font-weight: 600; }
  .score-card .pc { font-size: .9rem; color: var(--muted); font-weight: 600; }
  .bar { height: 9px; border-radius: 99px; background: #f0ecf6; margin-top: 12px; overflow: hidden; }
  .bar i { display: block; height: 100%; border-radius: 99px; background: var(--grad); transition: width .8s var(--ease); }
  .sum-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 14px; margin-top: 12px; box-shadow: var(--shadow); }
  .sum-card h3 { font-size: .88rem; font-weight: 600; color: var(--muted); display: flex; align-items: center; gap: 8px; margin-bottom: 11px; }
  .sum-grid { display: grid; grid-template-columns: repeat(10, 1fr); gap: 6px; }
  .sum-grid b { aspect-ratio: 1 / 1; display: grid; place-items: center; border-radius: 7px; font-size: .74rem; font-weight: 700; }
  .sum-grid b.ok { background: #dcfce7; color: #15803d; }
  .sum-grid b.no { background: #fee2e2; color: #b91c1c; }
  .sum-grid b.sk { background: #f1f5f9; color: #64748b; }
  .rev { margin-top: 12px; display: none; }
  .rev.show { display: block; }
  .rev-q { background: var(--card); border: 1px solid var(--line); border-left: 4px solid var(--line-2); border-radius: 13px; padding: 13px 14px; margin-bottom: 10px; box-shadow: var(--shadow); }
  .rev-q.ok { border-left-color: var(--green); }
  .rev-q.no { border-left-color: var(--red); }
  .rev-q.sk { border-left-color: #94a3b8; }
  .rev-q .rq { font-size: .92rem; font-weight: 600; line-height: 1.45; }
  .rev-q .rq span { color: var(--purple); }
  .rev-a { display: flex; gap: 8px; align-items: flex-start; font-size: .83rem; margin-top: 8px; }
  .rev-a .tag { flex: none; font-size: .68rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; padding: 2px 8px; border-radius: 99px; margin-top: 2px; }
  .rev-a.you .tag { background: #fdecf1; color: var(--red); }
  .rev-a.you.ok .tag, .rev-a.cor .tag { background: var(--green-bg); color: var(--green); }
  .rev-exp { margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--line-2); font-size: .8rem; color: var(--muted); line-height: 1.55; }

  .toast {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 200;
    background: #1f1430; color: #fff; padding: 11px 18px; border-radius: 10px;
    font-size: .84rem; box-shadow: 0 10px 24px rgba(0,0,0,.25); pointer-events: none;
  }

  @media (prefers-reduced-motion: reduce) { * { transition-duration: .01ms !important; animation: none !important; } }
</style>
</head>
<body>

<!-- ══════════════════ 1. START ══════════════════ -->
<section class="screen show" id="scrStart">
  <div class="wrap">
    <div class="start-head">
      <div class="start-logo" data-logo></div>
      <div class="start-name">DIPLOMA WALLAH</div>
      <div class="start-rule"></div>
      <h1 class="start-title">MCQ <span>TEST</span></h1>
    </div>

    <div class="chapter-card">
      <svg class="ic" viewBox="-2 -2 68 68" fill="none" stroke="#fff" stroke-width="2.5" stroke-linecap="round" aria-hidden="true">
        <circle cx="32" cy="32" r="4.6" fill="#fff" stroke="none"/>
        <ellipse cx="32" cy="32" rx="25" ry="10"/>
        <ellipse cx="32" cy="32" rx="25" ry="10" transform="rotate(60 32 32)"/>
        <ellipse cx="32" cy="32" rx="25" ry="10" transform="rotate(120 32 32)"/>
        <circle cx="54" cy="21" r="3.1" fill="#fff" stroke="none"/>
        <circle cx="12" cy="40" r="3.1" fill="#fff" stroke="none"/>
      </svg>
      <div class="tx">
        <h2><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></h2>
        <div class="chip-row">
          <?php foreach ($TEST['tags'] as $t): ?><span class="chip"><?= htmlspecialchars($t, ENT_QUOTES) ?></span><?php endforeach; ?>
        </div>
      </div>
    </div>

    <div class="sub-line"><?= htmlspecialchars($TEST['subtitle'], ENT_QUOTES) ?></div>

    <div class="stat-card">
      <div class="stat-row">
        <div class="stat"><div class="si"><i class="fa fa-file-lines"></i></div><div><div class="sl">Questions</div><div class="sv"><?= $QN ?></div></div></div>
        <div class="stat rose"><div class="si"><i class="fa fa-trophy"></i></div><div><div class="sl">Total Marks</div><div class="sv"><?= $num($totalMarks) ?></div></div></div>
      </div>
      <div class="stat-row">
        <div class="stat"><div class="si"><i class="fa fa-clock"></i></div><div><div class="sl">Total Time</div><div class="sv"><?= (int)$TEST['total_minutes'] ?> Minutes</div></div></div>
        <div class="stat rose"><div class="si"><i class="fa fa-star"></i></div><div><div class="sl">Per Question</div><div class="sv"><?= $num($TEST['mark']) ?> Mark</div></div></div>
      </div>
      <?php if ((int)$TEST['per_question_sec'] > 0): ?>
      <div class="stat-row">
        <div class="stat full"><div class="si"><i class="fa fa-stopwatch"></i></div><div><div class="sl">Time per Question</div><div class="sv"><?= (int)$TEST['per_question_sec'] ?> Seconds</div></div></div>
      </div>
      <?php endif; ?>
      <?php if ($TEST['negative'] > 0): ?>
      <div class="stat-row">
        <div class="stat rose full"><div class="si"><i class="fa fa-circle-minus"></i></div><div><div class="sl">Negative Marking</div><div class="sv"><?= $num($TEST['negative']) ?> Mark per wrong answer</div></div></div>
      </div>
      <?php endif; ?>
    </div>

    <div class="rules">
      <div class="rules-head"><i class="fa fa-list-check"></i> TEST RULES</div>
      <ol>
        <?php $r = 0; ?>
        <li><span class="n"><?= ++$r ?></span><span>Each correct answer carries <b><?= $num($TEST['mark']) ?> mark</b>.</span></li>
        <?php if ($TEST['negative'] > 0): ?>
        <li><span class="n"><?= ++$r ?></span><span><b><?= $num($TEST['negative']) ?> mark</b> is deducted for every wrong answer.</span></li>
        <?php endif; ?>
        <li><span class="n"><?= ++$r ?></span><span>Unanswered questions carry no marks and no penalty.</span></li>
        <?php if ((int)$TEST['per_question_sec'] > 0): ?>
        <li><span class="n"><?= ++$r ?></span><span>Each question has a time limit of <b><?= (int)$TEST['per_question_sec'] ?> seconds</b>. Once it ends, the question is closed.</span></li>
        <?php endif; ?>
        <li><span class="n"><?= ++$r ?></span><span>You may revisit and change any answered question before submitting.</span></li>
        <li><span class="n"><?= ++$r ?></span><span>The test is submitted automatically when the overall timer reaches zero.</span></li>
      </ol>
    </div>

    <div class="resume-note" id="resumeNote" style="display:none">
      <i class="fa fa-rotate-left" style="margin-top:3px"></i>
      <span>You have an unfinished attempt. <b id="resumeInfo"></b></span>
    </div>

    <div style="margin-top:16px">
      <button class="btn-primary" id="btnStart"><i class="fa fa-play"></i> START TEST <i class="fa fa-arrow-right"></i></button>
      <button class="btn-ghost" id="btnResume" style="display:none;margin-top:10px"><i class="fa fa-rotate-left"></i> Resume previous attempt</button>
    </div>

    <div class="powered">Powered by DIPLOMA WALLAH</div>
  </div>
</section>

<!-- ══════════════════ 2. TEST ══════════════════ -->
<section class="screen" id="scrTest">
  <div class="test-shell">
    <header class="topbar">
      <div class="topbar-in">
        <button class="icon-btn" id="btnPalette" aria-label="Question palette"><i class="fa fa-bars"></i></button>
        <div class="bl" data-logo></div>
        <div class="tt"><b>DIPLOMA WALLAH</b><small>Learn • Practice • Grow</small></div>
        <button class="icon-btn" id="btnInstr" aria-label="Instructions"><i class="fa fa-circle-info"></i></button>
      </div>
    </header>

    <div class="test-main">
      <div class="qcard">
        <div class="qfix">
          <div class="qtop">
            <div class="bk"><i class="fa fa-book-open"></i></div>
            <div class="ch"><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></div>
            <div class="cnt"><span id="qNow">1</span><span> / <?= $QN ?></span></div>
          </div>
          <div class="timers">
            <div class="timer" id="tOverall">
              <div class="ti"><i class="fa fa-hourglass-half"></i></div>
              <div><div class="tl">Overall Time</div><div class="tv" id="tOverallVal">20:00</div></div>
            </div>
            <div class="timer q" id="tQuestion">
              <div class="ti"><i class="fa fa-stopwatch"></i></div>
              <div><div class="tl">Question Time</div><div class="tv" id="tQuestionVal">01:00</div></div>
            </div>
          </div>
          <div class="qhead">
            <div class="qnum" id="qLabel">Question 1</div>
            <button class="bookmark" id="btnMark"><i class="fa-regular fa-bookmark"></i> <span>Bookmark</span></button>
          </div>
        </div>

        <div class="qmid" id="qScroll">
          <div class="qtext" id="qText">—</div>
          <div id="qDead"></div>
          <div class="opts" id="qOpts"></div>
        </div>

        <div class="qbot">
          <div class="act-row">
            <button class="btn-sm" id="btnClear"><i class="fa fa-eraser"></i> Clear</button>
            <button class="btn-sm grad" id="btnSaveNext">Save &amp; Next <i class="fa fa-arrow-right"></i></button>
          </div>
          <div class="act-row two">
            <button class="btn-sm" id="btnPrev"><i class="fa fa-arrow-left"></i> Previous</button>
            <button class="btn-sm" id="btnNext">Next <i class="fa fa-arrow-right"></i></button>
          </div>
          <div class="statusbar">
            <span><i class="dot ans"></i> Answered <b id="cAns">0</b></span>
            <span><i class="dot not"></i> Left <b id="cNot"><?= $QN ?></b></span>
            <span><i class="dot mark"></i> Marked <b id="cMark">0</b></span>
            <button class="btn-submit" id="btnSubmit"><i class="fa fa-paper-plane"></i> SUBMIT</button>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- palette sheet -->
<div class="scrim" id="scrim"></div>
<div class="sheet" id="sheet">
  <div class="sheet-in">
    <div class="sheet-box">
      <div class="sheet-bar">
        <div class="bl" data-logo></div>
        <div class="tt"><b>DIPLOMA WALLAH</b><small>Learn • Practice • Grow</small></div>
        <button class="icon-btn" id="btnSheetClose" aria-label="Close"><i class="fa fa-xmark"></i></button>
      </div>
      <div class="tabs">
        <button class="tab on" data-tab="pal">Question Palette</button>
        <button class="tab" data-tab="ins">Instructions</button>
      </div>
      <div class="pal-body">
        <div class="tab-pane show" id="paneP">
          <div class="pal-grid" id="palGrid"></div>
          <div class="pal-note"><i class="fa fa-circle-info"></i><span>Select any question number to go directly to that question. Answered questions can be reviewed and changed at any time before you submit the test.</span></div>
        </div>
        <div class="tab-pane" id="paneI">
          <ul class="ins-list">
            <li><i class="fa fa-circle-check"></i><span>Every correct answer carries <b><?= $num($TEST['mark']) ?> mark</b>.</span></li>
            <?php if ($TEST['negative'] > 0): ?>
            <li><i class="fa fa-circle-minus"></i><span><b><?= $num($TEST['negative']) ?> mark</b> is deducted for each wrong answer. Unanswered questions carry no penalty.</span></li>
            <?php endif; ?>
            <?php if ((int)$TEST['per_question_sec'] > 0): ?>
            <li><i class="fa fa-stopwatch"></i><span>Each question is open for <b><?= (int)$TEST['per_question_sec'] ?> seconds</b>. When that time ends the test moves to the next question and the previous one is closed.</span></li>
            <?php endif; ?>
            <li><i class="fa fa-bookmark"></i><span>Use Bookmark to flag a question for a second look. Bookmarked questions appear in pink in the palette.</span></li>
            <li><i class="fa fa-paper-plane"></i><span>The test is submitted automatically once the overall timer reaches zero.</span></li>
          </ul>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══════════════════ 3. RESULT ══════════════════ -->
<section class="screen" id="scrResult">
  <header class="topbar">
    <div class="topbar-in">
      <div class="bl" data-logo></div>
      <div class="tt"><b>DIPLOMA WALLAH</b><small>Learn • Practice • Grow</small></div>
    </div>
  </header>

  <div class="wrap">
    <div class="res-hero">
      <div class="tro">🏆</div>
      <h2>Test Completed!</h2>
      <p>Here is your result for <b><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></b></p>
    </div>

    <div class="res-cards">
      <div class="res-c ok"><i class="fa fa-circle-check"></i><div class="lb">Correct</div><div class="vl" id="rOk">0</div><div class="mk" id="rOkM">+0</div></div>
      <div class="res-c no"><i class="fa fa-circle-xmark"></i><div class="lb">Wrong</div><div class="vl" id="rNo">0</div><div class="mk" id="rNoM">-0</div></div>
      <div class="res-c sk"><i class="fa fa-circle-minus"></i><div class="lb">Unanswered</div><div class="vl" id="rSk">0</div><div class="mk">0 Marks</div></div>
    </div>

    <div class="score-card">
      <div class="md">🏅</div>
      <div class="lb">Your Score</div>
      <div class="sc"><span id="rScore">0</span><small> / <?= $num($totalMarks) ?></small></div>
      <div class="pc" id="rPct">(0%)</div>
      <div class="bar"><i id="rBar" style="width:0"></i></div>
      <div class="pc" style="margin-top:10px" id="rTime"></div>
    </div>

    <div class="sum-card">
      <h3><i class="fa fa-circle-info"></i> Question Summary</h3>
      <div class="sum-grid" id="sumGrid"></div>
    </div>

    <div class="act-row two" style="margin-top:14px">
      <button class="btn-sm" id="btnReview"><i class="fa fa-book-open"></i> Review Answers</button>
      <button class="btn-sm grad" id="btnAgain"><i class="fa fa-rotate-right"></i> Try Again</button>
    </div>
    <a class="btn-ghost" style="margin-top:9px" href="<?= htmlspecialchars($TEST['home_url'], ENT_QUOTES) ?>"><i class="fa fa-house"></i> Back to Home</a>

    <div class="rev" id="revBox"></div>
    <div class="powered">Powered by DIPLOMA WALLAH</div>
  </div>
</section>

<script>
(function () {
  "use strict";
  var T = <?= json_encode($CLIENT, JSON_UNESCAPED_UNICODE) ?>;
  var $ = function (id) { return document.getElementById(id); };
  var KEY = "d2d_mcqtest_" + (T.title || "test").toLowerCase().replace(/[^a-z0-9]+/g, "_");
  var LETTERS = ["A", "B", "C", "D", "E", "F"];
  var PER_Q = T.perQSec > 0;

  /* ---------- logo ---------- */
  (function () {
    var holders = document.querySelectorAll("[data-logo]");
    var probe = new Image();
    probe.onload = function () {
      [].forEach.call(holders, function (h) { h.innerHTML = '<img class="brand-logo" src="diplomawallah-logo.png" alt="Diploma Wallah">'; });
    };
    probe.onerror = function () {
      [].forEach.call(holders, function (h) { h.innerHTML = '<div class="logo-fallback"><i class="fa fa-book-open"></i></div>'; });
    };
    probe.src = "diplomawallah-logo.png";
  })();

  /* ---------- state ---------- */
  var st = null;
  function freshState() {
    var a = [], m = [], t = [];
    for (var i = 0; i < T.count; i++) { a.push(null); m.push(false); t.push(T.perQSec); }
    return { i: 0, ans: a, mark: m, qt: t, left: T.totalSec, done: false };
  }
  function save() { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) {} }
  function clearSaved() { try { localStorage.removeItem(KEY); } catch (e) {} }
  function loadSaved() {
    try {
      var s = JSON.parse(localStorage.getItem(KEY) || "null");
      if (!s || s.done || !s.ans || s.ans.length !== T.count) return null;
      return s;
    } catch (e) { return null; }
  }

  function show(id) {
    [].forEach.call(document.querySelectorAll(".screen"), function (s) { s.classList.toggle("show", s.id === id); });
    document.body.classList.toggle("mode-test", id === "scrTest");
    if (id !== "scrTest") window.scrollTo(0, 0);
  }

  /* ---------- start ---------- */
  (function () {
    var s = loadSaved();
    if (!s) return;
    var answered = s.ans.filter(function (x) { return x !== null; }).length;
    $("resumeNote").style.display = "flex";
    $("resumeInfo").textContent = answered + " of " + T.count + " answered, " + fmt(s.left) + " remaining.";
    $("btnResume").style.display = "flex";
    $("btnResume").addEventListener("click", function () { st = s; show("scrTest"); startTimer(); renderQ(); });
  })();
  $("btnStart").addEventListener("click", function () {
    clearSaved(); st = freshState(); show("scrTest"); startTimer(); renderQ();
  });

  /* ---------- timers ---------- */
  var tick = null;
  function fmt(s) {
    s = Math.max(0, Math.round(s));
    var m = Math.floor(s / 60), r = s % 60;
    return (m < 10 ? "0" : "") + m + ":" + (r < 10 ? "0" : "") + r;
  }
  function startTimer() {
    if (tick) clearInterval(tick);
    tick = setInterval(function () {
      if (!st || st.done) return;
      st.left--;
      /* sirf us tick par react karo jab timer abhi-abhi 0 hua ho — warna
         khatam ho chuke question par wapas aate hi baar baar aage phenk dega */
      var wasRunning = PER_Q && st.qt[st.i] > 0;
      if (wasRunning) st.qt[st.i]--;
      paintTimers();
      if (st.left <= 0) { finish("Time over — the test was submitted automatically."); return; }
      if (wasRunning && st.qt[st.i] <= 0) {
        renderQ();
        if (st.i < T.count - 1) { go(st.i + 1); toast("Time up for this question."); }
      }
      if (st.left % 5 === 0) save();
    }, 1000);
    paintTimers();
  }
  function paintTimers() {
    $("tOverallVal").textContent = fmt(st.left);
    $("tOverall").classList.toggle("warn", st.left <= 60);
    var box = $("tQuestion");
    if (!PER_Q) { box.classList.add("off"); $("tQuestionVal").textContent = "--:--"; return; }
    $("tQuestionVal").textContent = fmt(st.qt[st.i]);
    box.classList.toggle("warn", st.qt[st.i] <= 10);
  }

  /* ---------- question ---------- */
  function renderQ() {
    var i = st.i, q = T.questions[i];
    $("qNow").textContent = i + 1;
    $("qLabel").textContent = "Question " + (i + 1);
    $("qText").textContent = q.q;

    var dead = PER_Q && st.qt[i] <= 0;
    $("qDead").innerHTML = dead ? '<div class="timeover"><i class="fa fa-hourglass-end"></i> Time is up for this question</div>' : "";

    var html = "";
    for (var k = 0; k < q.o.length; k++) {
      html += '<button class="opt' + (st.ans[i] === k ? " sel" : "") + (dead ? " dead" : "") + '" data-k="' + k + '">' +
              '<span class="k">' + LETTERS[k] + '</span><span class="v"></span></button>';
    }
    $("qOpts").innerHTML = html;
    [].forEach.call($("qOpts").children, function (b, k) { b.querySelector(".v").textContent = q.o[k]; });

    var mk = $("btnMark");
    mk.classList.toggle("on", !!st.mark[i]);
    mk.innerHTML = st.mark[i]
      ? '<i class="fa-solid fa-bookmark"></i> <span>Bookmarked</span>'
      : '<i class="fa-regular fa-bookmark"></i> <span>Bookmark</span>';

    $("btnPrev").disabled = i === 0;
    $("btnNext").disabled = i === T.count - 1;
    $("btnSaveNext").innerHTML = (i === T.count - 1)
      ? 'Save &amp; Finish <i class="fa fa-flag-checkered"></i>'
      : 'Save &amp; Next <i class="fa fa-arrow-right"></i>';

    paintTimers(); paintCounts(); paintPalette();
  }
  function paintCounts() {
    var a = 0, m = 0;
    for (var i = 0; i < T.count; i++) { if (st.ans[i] !== null) a++; if (st.mark[i]) m++; }
    $("cAns").textContent = a; $("cNot").textContent = T.count - a; $("cMark").textContent = m;
  }
  function go(i) {
    if (i < 0 || i >= T.count) return;
    st.i = i; save(); renderQ();
    $("qScroll").scrollTop = 0;          /* sirf question area reset — baaki screen hilegi nahi */
  }

  $("qOpts").addEventListener("click", function (e) {
    var b = e.target.closest(".opt"); if (!b) return;
    st.ans[st.i] = parseInt(b.getAttribute("data-k"), 10);
    save(); renderQ();
  });
  $("btnClear").addEventListener("click", function () { st.ans[st.i] = null; save(); renderQ(); });
  $("btnMark").addEventListener("click", function () { st.mark[st.i] = !st.mark[st.i]; save(); renderQ(); });
  $("btnPrev").addEventListener("click", function () { go(st.i - 1); });
  $("btnNext").addEventListener("click", function () { go(st.i + 1); });
  $("btnSaveNext").addEventListener("click", function () {
    if (st.i === T.count - 1) { askSubmit(); return; }
    go(st.i + 1);
  });
  $("btnSubmit").addEventListener("click", askSubmit);
  function askSubmit() {
    var left = 0;
    for (var i = 0; i < T.count; i++) if (st.ans[i] === null) left++;
    var msg = left ? (left + " question" + (left > 1 ? "s are" : " is") + " unanswered. Submit anyway?") : "Submit the test?";
    if (confirm(msg)) finish("");
  }

  /* ---------- palette ---------- */
  function paintPalette() {
    var h = "";
    for (var i = 0; i < T.count; i++) {
      var cls = st.ans[i] !== null ? "ans" : "";
      if (st.mark[i]) cls = "mark";
      if (i === st.i) cls += " cur";
      h += '<button class="pal-btn ' + cls + '" data-i="' + i + '">' + (i + 1) + "</button>";
    }
    $("palGrid").innerHTML = h;
  }
  function openSheet(tab) { paintPalette(); switchTab(tab); $("sheet").classList.add("show"); $("scrim").classList.add("show"); }
  function closeSheet() { $("sheet").classList.remove("show"); $("scrim").classList.remove("show"); }
  function switchTab(which) {
    [].forEach.call(document.querySelectorAll(".tab"), function (t) { t.classList.toggle("on", t.getAttribute("data-tab") === which); });
    $("paneP").classList.toggle("show", which === "pal");
    $("paneI").classList.toggle("show", which === "ins");
  }
  $("btnPalette").addEventListener("click", function () { openSheet("pal"); });
  $("btnInstr").addEventListener("click", function () { openSheet("ins"); });
  $("btnSheetClose").addEventListener("click", closeSheet);
  $("scrim").addEventListener("click", closeSheet);
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") closeSheet(); });
  [].forEach.call(document.querySelectorAll(".tab"), function (t) {
    t.addEventListener("click", function () { switchTab(t.getAttribute("data-tab")); });
  });
  $("palGrid").addEventListener("click", function (e) {
    var b = e.target.closest(".pal-btn"); if (!b) return;
    closeSheet(); go(parseInt(b.getAttribute("data-i"), 10));
  });

  var toastT = null;
  function toast(msg) {
    var d = document.querySelector(".toast");
    if (!d) { d = document.createElement("div"); d.className = "toast"; document.body.appendChild(d); }
    d.textContent = msg;
    if (toastT) clearTimeout(toastT);
    toastT = setTimeout(function () { d.remove(); }, 1900);
  }

  /* ---------- result ---------- */
  function finish(note) {
    if (st.done) return;
    st.done = true;
    if (tick) clearInterval(tick);
    closeSheet(); clearSaved();

    var ok = 0, no = 0, sk = 0, i;
    for (i = 0; i < T.count; i++) {
      if (st.ans[i] === null) sk++;
      else if (st.ans[i] === T.questions[i].a) ok++;
      else no++;
    }
    var score = ok * T.mark - no * T.negative;
    var total = T.count * T.mark;
    var pct = total ? (score / total) * 100 : 0;
    var n = function (x) { return (Math.round(x * 100) / 100).toString(); };

    $("rOk").textContent = ok;  $("rOkM").textContent = "+" + n(ok * T.mark) + " Marks";
    $("rNo").textContent = no;  $("rNoM").textContent = "-" + n(no * T.negative) + " Marks";
    $("rSk").textContent = sk;
    $("rScore").textContent = n(score);
    $("rPct").textContent = "(" + n(Math.max(0, pct)) + "%)";
    $("rBar").style.width = Math.max(0, Math.min(100, pct)) + "%";
    $("rTime").textContent = (note ? note + " " : "") + "Time used: " + fmt(T.totalSec - Math.max(0, st.left)) + " of " + fmt(T.totalSec) + ".";

    var g = "";
    for (i = 0; i < T.count; i++) {
      var c = st.ans[i] === null ? "sk" : (st.ans[i] === T.questions[i].a ? "ok" : "no");
      g += "<b class='" + c + "'>" + (i + 1) + "</b>";
    }
    $("sumGrid").innerHTML = g;

    buildReview();
    show("scrResult");
  }
  function buildReview() {
    var h = "";
    for (var i = 0; i < T.count; i++) {
      var q = T.questions[i], you = st.ans[i];
      var cls = you === null ? "sk" : (you === q.a ? "ok" : "no");
      h += '<div class="rev-q ' + cls + '">' +
             '<div class="rq"><span>Q' + (i + 1) + '.</span> ' + esc(q.q) + "</div>" +
             '<div class="rev-a you' + (you !== null && you === q.a ? " ok" : "") + '"><span class="tag">' +
               (you === null ? "Not answered" : "Your answer") + '</span><span>' +
               (you === null ? "—" : LETTERS[you] + ") " + esc(q.o[you])) + "</span></div>" +
             (you === q.a ? "" :
               '<div class="rev-a cor"><span class="tag">Correct</span><span>' + LETTERS[q.a] + ") " + esc(q.o[q.a]) + "</span></div>") +
             (q.e ? '<div class="rev-exp"><b>Explanation:</b> ' + esc(q.e) + "</div>" : "") +
           "</div>";
    }
    $("revBox").innerHTML = h;
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }
  $("btnReview").addEventListener("click", function () {
    var box = $("revBox"), on = box.classList.toggle("show");
    this.innerHTML = on ? '<i class="fa fa-eye-slash"></i> Hide Review' : '<i class="fa fa-book-open"></i> Review Answers';
    if (on) box.scrollIntoView({ behavior: "smooth", block: "start" });
  });
  $("btnAgain").addEventListener("click", function () {
    clearSaved(); st = null;
    $("revBox").classList.remove("show");
    $("btnReview").innerHTML = '<i class="fa fa-book-open"></i> Review Answers';
    $("resumeNote").style.display = "none"; $("btnResume").style.display = "none";
    show("scrStart");
  });

  window.addEventListener("beforeunload", function (e) {
    if (st && !st.done) { save(); e.preventDefault(); e.returnValue = ""; }
  });
})();
</script>
</body>
</html>
