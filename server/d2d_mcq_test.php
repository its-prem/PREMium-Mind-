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
         'e' => 'Carbon has 6 protons in its nucleus, so its atomic number $Z = 6$.'],

        ['q' => 'Which sub-atomic particle carries a negative charge?',
         'o' => ['Proton', 'Neutron', 'Electron', 'Nucleus'], 'a' => 2,
         'e' => 'The electron carries a charge of $-1.6 \times 10^{-19}$ C. Protons are positive and neutrons are neutral.'],

        ['q' => 'The mass of a proton is approximately',
         'o' => ['$9.11 \times 10^{-31}$ kg', '$1.67 \times 10^{-27}$ kg', '$1.6 \times 10^{-19}$ kg', '$6.63 \times 10^{-34}$ kg'], 'a' => 1,
         'e' => 'A proton weighs about $1.67 \times 10^{-27}$ kg, roughly 1836 times the mass of an electron.'],

        ['q' => 'The electron was discovered by',
         'o' => ['Rutherford', 'J. J. Thomson', 'Chadwick', 'Niels Bohr'], 'a' => 1,
         'e' => 'J. J. Thomson discovered the electron in 1897 through his cathode ray tube experiments.'],

        ['q' => 'The neutron was discovered by',
         'o' => ['James Chadwick', 'J. J. Thomson', 'John Dalton', 'Henry Moseley'], 'a' => 0,
         'e' => 'James Chadwick discovered the neutron in 1932 and won the Nobel Prize for it in 1935.'],

        ['q' => "Rutherford's $\alpha$-particle scattering experiment proved the existence of the",
         'o' => ['Electron', 'Nucleus', 'Neutron', 'Isotope'], 'a' => 1,
         'e' => 'Most $\alpha$-particles passed straight through, but a few bounced back, proving a small dense positive nucleus.'],

        ['q' => 'The maximum number of electrons that the L shell can hold is',
         'o' => ['2', '8', '18', '32'], 'a' => 1,
         'e' => 'Capacity of a shell is $2n^2$. For the L shell $n = 2$, so $2 \times 2^2 = 8$ electrons.'],

        ['q' => 'Isotopes of an element always have the same',
         'o' => ['Mass number', 'Number of neutrons', 'Atomic number', 'Number of nucleons'], 'a' => 2,
         'e' => 'Isotopes have the same number of protons (same $Z$) but a different number of neutrons.'],

        ['q' => 'The charge on one electron is',
         'o' => ['$+1.6 \times 10^{-19}$ C', '$-1.6 \times 10^{-19}$ C', '$-9.1 \times 10^{-31}$ C', 'Zero'], 'a' => 1,
         'e' => 'An electron carries one unit of negative charge, $-1.6 \times 10^{-19}$ coulomb.'],

        ['q' => "In Bohr's model the angular momentum of an electron is an integral multiple of",
         'o' => ['$h$', '$\frac{h}{2\pi}$', '$2\pi h$', '$\frac{h}{\pi}$'], 'a' => 1,
         'e' => 'Bohr quantisation condition: $mvr = \frac{nh}{2\pi}$, where $n = 1, 2, 3 \ldots$'],

        ['q' => 'The number of neutrons in a sodium atom ($Z = 11$, $A = 23$) is',
         'o' => ['11', '12', '23', '34'], 'a' => 1,
         'e' => 'Neutrons $= A - Z = 23 - 11 = 12$.'],

        ['q' => 'Which of the following is a pair of isobars?',
         'o' => ['$^{14}C$ and $^{14}N$', '$^{1}H$ and $^{2}H$', '$^{35}Cl$ and $^{37}Cl$', '$^{16}O$ and $^{18}O$'], 'a' => 0,
         'e' => 'Isobars have the same mass number but different atomic numbers. $^{14}C$ and $^{14}N$ both have $A = 14$.'],

        ['q' => 'The energy of an electron in the $n$th orbit of a hydrogen atom is proportional to',
         'o' => ['$n$', '$n^2$', '$\frac{1}{n}$', '$\frac{1}{n^2}$'], 'a' => 3,
         'e' => '$E_n = -\frac{13.6}{n^2}$ eV, so the energy varies as $\frac{1}{n^2}$.'],

        ['q' => 'The radius of the first Bohr orbit of hydrogen is about',
         'o' => ['$0.529$ Å', '$1.00$ Å', '$2.50$ Å', '$5.29$ Å'], 'a' => 0,
         'e' => 'The Bohr radius $a_0 = 0.529$ Å $= 0.529 \times 10^{-10}$ m.'],

        ['q' => 'The shape of an orbital is decided by which quantum number?',
         'o' => ['Principal ($n$)', 'Azimuthal ($l$)', 'Magnetic ($m$)', 'Spin ($s$)'], 'a' => 1,
         'e' => 'The azimuthal quantum number $l$ gives the sub-shell and hence the shape: $l = 0$ is s (spherical), $l = 1$ is p (dumb-bell).'],

        ['q' => 'The maximum number of electrons in a d sub-shell is',
         'o' => ['2', '6', '10', '14'], 'a' => 2,
         'e' => 'A d sub-shell has 5 orbitals and each orbital holds 2 electrons, so 10 in total.'],

        ['q' => "Pauli's exclusion principle states that no two electrons in an atom can have",
         'o' => ['The same spin', 'The same energy', 'All four quantum numbers the same', 'The same orbital'], 'a' => 2,
         'e' => 'Two electrons may share an orbital only if their spins differ, so all four quantum numbers can never match.'],

        ['q' => 'The electronic configuration of chlorine ($Z = 17$) is',
         'o' => ['2, 8, 7', '2, 8, 8', '2, 8, 6', '2, 7, 8'], 'a' => 0,
         'e' => 'K = 2, L = 8, M = 7. Chlorine needs one more electron to complete its octet.'],

        ['q' => 'An atom as a whole is electrically neutral because',
         'o' => ['It contains no charge', 'Protons equal electrons in number', 'It contains neutrons', 'The nucleus is positive'], 'a' => 1,
         'e' => 'Equal numbers of protons and electrons make the positive and negative charges cancel out.'],

        ['q' => 'The de Broglie wavelength of a moving particle is given by',
         'o' => ['$\lambda = \frac{h}{p}$', '$\lambda = \frac{p}{h}$', '$\lambda = hp$', '$\lambda = \frac{h}{mc^2}$'], 'a' => 0,
         'e' => 'de Broglie relation: $\lambda = \frac{h}{p} = \frac{h}{mv}$. Heavier or faster particles have a shorter wavelength.'],
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
<meta name="theme-color" content="#8e1b2a">
<title>MCQ Test — <?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?> | Diploma Wallah</title>
<link rel="icon" href="diplomawallah-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Oswald:wght@400;500;700&family=Poppins:wght@400;500;600;700&family=Tinos:wght@400;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
  :root {
    /* Notes/MCQ sheet wali hi maroon theme */
    --purple: #8e1b2a;          /* primary */
    --purple-2: #6f1220;        /* primary hover */
    --crimson: #8e1b2a;         /* accent = same maroon family */
    --grad: linear-gradient(180deg, #9a1f2f 0%, #8e1b2a 55%, #7d1524 100%);
    --ink: #0f172a;
    --muted: #64748b;
    --line: #e2e8f0;
    --line-2: #cbd5e1;
    --bg: #f1f5f9;
    --card: #ffffff;
    --tint: #fbecef;            /* primary-light */
    --green: #16a34a;
    --green-bg: #e9f9ef;
    --red: #e11d48;
    --ease: cubic-bezier(.4, 0, .2, 1);
    --shadow: 0 4px 16px rgba(15, 23, 42, .06);
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
      radial-gradient(60vw 40vw at 85% -5%, rgba(142, 27, 42, .07), transparent 65%),
      radial-gradient(55vw 40vw at -10% 8%, rgba(142, 27, 42, .06), transparent 65%);
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
    box-shadow: 0 8px 20px rgba(142, 27, 42, .28); transition: transform .15s var(--ease);
  }
  .btn-primary:active { transform: scale(.985); }
  .btn-ghost {
    display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%;
    background: #fff; color: var(--ink); font-weight: 600; font-size: .92rem;
    padding: 13px 16px; border-radius: 12px; border: 1px solid var(--line-2); text-decoration: none;
  }
  .btn-ghost:active { background: #f8fafc; }
  .powered { text-align: center; color: var(--muted); font-size: .76rem; margin-top: 18px; display: flex; align-items: center; gap: 10px; justify-content: center; }
  .powered::before, .powered::after { content: ""; height: 1px; width: 26px; background: var(--line-2); }

  /* ============ START ============ */
  .start-head { text-align: center; padding: 26px 0 6px; }
  .start-logo { width: 122px; height: 122px; margin: 0 auto 10px; }
  .start-name { font-family: 'Oswald', sans-serif; font-weight: 700; letter-spacing: 3px; font-size: 1rem; color: var(--purple); text-transform: uppercase; }
  .start-rule { width: 54px; height: 3px; border-radius: 3px; background: var(--grad); margin: 8px auto 16px; }
  .start-title { font-family: 'Oswald', sans-serif; font-size: 2.6rem; font-weight: 700; letter-spacing: 1px; line-height: 1.05; text-transform: uppercase; }
  .start-title span { color: var(--crimson); }

  .chip { background: rgba(255, 255, 255, .18); border: 1px solid rgba(255, 255, 255, .3); color: #fff; font-size: .7rem; font-weight: 600; padding: 3px 11px; border-radius: 99px; }
  .chip-row { display: flex; gap: 7px; flex-wrap: wrap; margin-top: 7px; }
  .chapter-card { background: var(--grad); color: #fff; border-radius: 16px; padding: 16px 18px; margin-top: 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 10px 24px rgba(110, 18, 32, .26); }
  .chapter-card .ic { width: 42px; height: 42px; flex: none; opacity: .92; }
  .chapter-card .tx { min-width: 0; flex: 1; }
  .chapter-card h2 { font-family: 'Oswald', sans-serif; font-size: 1.3rem; font-weight: 700; letter-spacing: .4px; line-height: 1.2; text-transform: uppercase; }
  .sub-line { display: flex; align-items: center; gap: 10px; justify-content: center; color: var(--muted); font-size: .85rem; margin: 14px 0 16px; }
  .sub-line::before, .sub-line::after { content: ""; height: 1px; width: 24px; background: var(--line-2); }

  .stat-card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .stat-row { display: grid; grid-template-columns: 1fr 1fr; }
  .stat-row + .stat-row { border-top: 1px solid var(--line); }
  .stat { display: flex; align-items: center; gap: 11px; padding: 13px 14px; min-width: 0; }
  .stat + .stat { border-left: 1px solid var(--line); }
  .stat.full { grid-column: 1 / -1; }
  .stat .si { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; font-size: .95rem; background: var(--tint); color: var(--purple); }
  .stat.rose .si { background: var(--tint); color: var(--crimson); }
  .stat .sl { font-size: .74rem; color: var(--muted); line-height: 1.3; }
  .stat .sv { font-size: 1.05rem; font-weight: 700; color: var(--purple); line-height: 1.25; }
  .stat.rose .sv { color: var(--crimson); }

  .rules { margin-top: 16px; border-radius: 16px; overflow: hidden; box-shadow: var(--shadow); border: 1px solid var(--line); }
  .rules-head { background: var(--grad); color: #fff; padding: 13px 16px; font-family: 'Oswald', sans-serif; font-weight: 500; text-transform: uppercase; font-weight: 700; letter-spacing: 1px; display: flex; align-items: center; gap: 10px; font-size: .95rem; }
  .rules ol { list-style: none; background: var(--card); padding: 6px 14px 12px; }
  .rules li { display: flex; gap: 12px; align-items: flex-start; padding: 9px 0; font-size: .87rem; color: #40474f; }
  .rules li + li { border-top: 1px solid var(--line); }
  .rules li .n { width: 24px; height: 24px; flex: none; border-radius: 50%; background: var(--tint); color: var(--purple); font-size: .74rem; font-weight: 700; display: grid; place-items: center; margin-top: 1px; }
  .rules li b { color: var(--crimson); font-weight: 700; }
  .resume-note { margin-top: 14px; background: #fff8ea; border: 1px solid #f6e2bb; color: #8a5a00; border-radius: 12px; padding: 11px 14px; font-size: .82rem; display: flex; gap: 10px; align-items: flex-start; }

  /* ============ TEST (fixed shell — only the question area moves) ============ */
  .test-shell { height: 100dvh; display: flex; flex-direction: column; overflow: hidden; }
  .topbar { flex: none; background: var(--grad); color: #fff; box-shadow: 0 2px 14px rgba(110, 18, 32, .22); }
  .topbar-in { max-width: 520px; margin: 0 auto; padding: 10px 14px; display: flex; align-items: center; gap: 12px; }
  .icon-btn { width: 38px; height: 38px; flex: none; border-radius: 11px; display: grid; place-items: center; color: #fff; font-size: 1.05rem; background: rgba(255,255,255,.12); }
  .icon-btn:active { background: rgba(255,255,255,.22); }
  .topbar .bl { width: 36px; height: 36px; flex: none; border-radius: 50%; background: #fff; padding: 2px; overflow: hidden; }
  .topbar .tt { flex: 1; min-width: 0; }
  .topbar .tt b { display: block; font-family: 'Oswald', sans-serif; font-size: 1rem; font-weight: 700; letter-spacing: 1.2px; line-height: 1.2; text-transform: uppercase; }
  .topbar .tt small { display: block; font-size: .6rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }

  .test-main { flex: 1 1 auto; min-height: 0; display: flex; justify-content: center; padding: 12px 16px 14px; }
  .qcard { width: 100%; max-width: 520px; display: flex; flex-direction: column; min-height: 0;
           background: var(--card); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow); overflow: hidden; }
  .qfix { flex: none; padding: 13px 14px 0; }
  .qmid { flex: 1 1 auto; min-height: 0; overflow-y: auto; overscroll-behavior: contain; padding: 0 14px; display: flex; }
  .qmid-in { width: 100%; margin: auto 0; padding: 8px 0 12px; }   /* question screen ke beech me rahe */
  .qbot { flex: none; padding: 10px 14px 13px; border-top: 1px solid var(--line); background: #f8fafc; }

  .qtop { display: flex; align-items: center; gap: 9px; }
  .qnum { background: var(--grad); color: #fff; font-family: 'Oswald', sans-serif; font-weight: 500; font-size: .86rem; padding: 6px 12px; border-radius: 9px; flex: none; letter-spacing: 1px; }
  .qtop .ch { flex: 1; min-width: 0; font-weight: 600; font-size: .9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
  .qtop .cnt { font-weight: 700; color: var(--purple); font-size: .9rem; flex: none; font-variant-numeric: tabular-nums; }
  .qtop .cnt span { color: var(--muted); font-weight: 500; }
  .bmk { width: 34px; height: 34px; flex: none; border-radius: 9px; display: grid; place-items: center; color: var(--muted); background: #f1f5f9; font-size: .9rem; }
  .bmk.on { color: var(--crimson); background: var(--tint); }

  .timers { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-top: 11px; }
  .timer { display: flex; align-items: center; gap: 10px; border-radius: 13px; padding: 9px 11px; border: 1px solid var(--line); background: #f8fafc; }
  .timer .ti { width: 32px; height: 32px; flex: none; border-radius: 50%; display: grid; place-items: center; font-size: .9rem; background: var(--tint); color: var(--purple); }
  .timer .tl { font-size: .68rem; color: var(--muted); line-height: 1.2; }
  .timer .tv { font-size: 1.1rem; font-weight: 700; color: var(--purple); font-variant-numeric: tabular-nums; letter-spacing: .5px; }
  .timer.q { background: #fff8f9; border-color: #f3dde1; }
  .timer.q .ti { background: var(--tint); color: var(--crimson); }
  .timer.q .tv { color: var(--crimson); }
  .timer.warn { animation: pulse 1s infinite; }
  @keyframes pulse { 50% { opacity: .5; } }
  .timer.off { opacity: .45; }

  .qtext { font-size: 1.02rem; font-weight: 600; line-height: 1.5; margin: 0 0 2px; }
  .timeover { display: inline-flex; align-items: center; gap: 7px; background: #fdeef2; color: var(--red); font-size: .76rem; font-weight: 600; padding: 5px 11px; border-radius: 99px; margin-top: 8px; }
  .opts { display: flex; flex-direction: column; gap: 9px; margin: 12px 0 14px; }
  .opt { display: flex; align-items: center; gap: 13px; width: 100%; text-align: left; background: #fff; border: 1.5px solid var(--line-2); border-radius: 13px; padding: 12px 13px; transition: border-color .15s var(--ease), background .15s var(--ease); }
  .opt .k { width: 33px; height: 33px; flex: none; border-radius: 10px; background: #f1f5f9; color: #475569; font-weight: 700; display: grid; place-items: center; font-size: .88rem; }
  .opt .v { flex: 1; min-width: 0; font-size: .94rem; font-weight: 500; overflow-wrap: anywhere; }
  .opt.sel { border-color: var(--purple-2); background: var(--tint); }
  .opt.sel .k { background: var(--grad); color: #fff; }
  .opt.sel .v { font-weight: 600; }
  .opt.dead { opacity: .62; pointer-events: none; }

  .act-row { display: grid; grid-template-columns: auto 1fr 1.55fr auto; gap: 8px; align-items: stretch; }
  .act-row.two { grid-template-columns: 1fr 1fr; margin-top: 9px; }
  .btn-ico { border-radius: 12px; border: 1px solid var(--line-2); background: #fff; display: grid; place-items: center; color: var(--ink); width: 44px; }
  .btn-ico:active { background: #f1f5f9; }
  .btn-ico:disabled { opacity: .4; pointer-events: none; }
  .btn-sm { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 10px; border-radius: 12px; font-weight: 600; font-size: .89rem; border: 1px solid var(--line-2); background: #fff; color: var(--ink); }
  .btn-sm:active { background: #f1f5f9; }
  .btn-sm.grad { background: var(--grad); color: #fff; border-color: transparent; box-shadow: 0 5px 14px rgba(142, 27, 42, .22); }
  .btn-sm:disabled { opacity: .45; pointer-events: none; }

  .statusbar { display: flex; align-items: center; gap: 9px; margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--line-2); flex-wrap: wrap; }
  .statusbar span { display: flex; align-items: center; gap: 5px; font-size: .71rem; color: #475569; white-space: nowrap; }
  .statusbar span b { font-weight: 700; color: var(--ink); }
  .dot { width: 10px; height: 10px; border-radius: 50%; flex: none; }
  .dot.ans { background: var(--green); }
  .dot.not { background: #fff; border: 1.5px solid var(--line-2); }
  .dot.mark { background: #f472b6; }
  .btn-submit { margin-left: auto; background: var(--grad); color: #fff; font-weight: 700; font-size: .76rem; letter-spacing: .4px; padding: 8px 15px; border-radius: 10px; display: flex; align-items: center; gap: 6px; box-shadow: 0 5px 14px rgba(142, 27, 42, .22); white-space: nowrap; }

  /* ============ PALETTE SHEET ============ */
  .scrim { position: fixed; inset: 0; background: rgba(15, 23, 42, .55); backdrop-filter: blur(2px); z-index: 60; opacity: 0; visibility: hidden; transition: opacity .25s, visibility .25s; }
  .scrim.show { opacity: 1; visibility: visible; }
  .sheet { position: fixed; left: 0; right: 0; top: 0; z-index: 70; transform: translateY(-104%); transition: transform .3s var(--ease); }
  .sheet.show { transform: none; }
  .sheet-in { max-width: 520px; margin: 0 auto; padding: 0 12px; }
  .sheet-box { background: #fff; border-radius: 0 0 20px 20px; box-shadow: 0 16px 40px rgba(15, 23, 42, .25); overflow: hidden; max-height: 92dvh; display: flex; flex-direction: column; }
  .sheet-bar { background: var(--grad); color: #fff; padding: 11px 14px; display: flex; align-items: center; gap: 12px; flex: none; }
  .sheet-bar .bl { width: 34px; height: 34px; border-radius: 50%; background: #fff; padding: 2px; flex: none; overflow: hidden; }
  .sheet-bar .tt { flex: 1; min-width: 0; }
  .sheet-bar .tt b { display: block; font-family: 'Oswald', sans-serif; font-size: .98rem; font-weight: 700; letter-spacing: 1.2px; text-transform: uppercase; }
  .sheet-bar .tt small { font-size: .58rem; letter-spacing: 1.6px; opacity: .82; text-transform: uppercase; }
  .tabs { display: grid; grid-template-columns: 1fr 1fr; gap: 8px; padding: 12px 14px 0; flex: none; }
  .tab { padding: 10px; border-radius: 10px; font-weight: 600; font-size: .86rem; background: #f1f5f9; color: var(--muted); }
  .tab.on { background: var(--grad); color: #fff; box-shadow: 0 5px 14px rgba(142, 27, 42, .2); }
  .pal-body { padding: 14px; overflow-y: auto; }
  .pal-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 9px; }
  .pal-btn { aspect-ratio: 1 / 1; border-radius: 10px; font-weight: 700; font-size: .92rem; border: 1.5px solid var(--line-2); background: #fff; color: #475569; display: grid; place-items: center; }
  .pal-btn.ans { background: #dcfce7; border-color: #86efac; color: #15803d; }
  .pal-btn.mark { background: #fce7f3; border-color: #f9a8d4; color: #be185d; }
  .pal-btn.cur { border-color: var(--purple-2); border-width: 2px; color: var(--purple); background: var(--tint); }
  .pal-note { display: flex; gap: 10px; align-items: flex-start; background: #f8fafc; border: 1px solid var(--line); border-radius: 11px; padding: 11px 12px; margin-top: 14px; font-size: .78rem; color: var(--muted); line-height: 1.55; }
  .pal-note i { color: var(--purple-2); margin-top: 2px; }
  .tab-pane { display: none; }
  .tab-pane.show { display: block; }
  .ins-list { list-style: none; }
  .ins-list li { display: flex; gap: 10px; padding: 9px 0; font-size: .85rem; color: #40474f; }
  .ins-list li + li { border-top: 1px solid var(--line); }
  .ins-list i { color: var(--purple-2); margin-top: 3px; }

  /* ============ RESULT ============ */
  .res-hero { background: var(--grad); color: #fff; border-radius: 18px; padding: 26px 18px 22px; text-align: center; margin-top: 16px; box-shadow: 0 12px 30px rgba(110, 18, 32, .28); position: relative; overflow: hidden; }
  .res-hero::after { content: ""; position: absolute; inset: 0; background: radial-gradient(60% 50% at 50% 0%, rgba(255,255,255,.18), transparent 70%); }
  .res-hero .tro { font-size: 2.9rem; position: relative; }
  .res-hero h2 { font-family: 'Oswald', sans-serif; font-size: 1.6rem; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; margin-top: 6px; position: relative; }
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
  .score-card .sc { font-family: 'Oswald', sans-serif; font-size: 2.6rem; font-weight: 700; color: var(--crimson); line-height: 1.1; }
  .score-card .sc small { font-size: 1.1rem; color: var(--muted); font-weight: 600; }
  .score-card .pc { font-size: .9rem; color: var(--muted); font-weight: 600; }
  .bar { height: 9px; border-radius: 99px; background: #e9eef4; margin-top: 12px; overflow: hidden; }
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
  .rev-a.you .tag { background: #fdeef2; color: var(--red); }
  .rev-a.you.ok .tag, .rev-a.cor .tag { background: var(--green-bg); color: var(--green); }
  .rev-exp { margin-top: 9px; padding-top: 9px; border-top: 1px dashed var(--line-2); font-size: .8rem; color: var(--muted); line-height: 1.55; }

  /* chhoti / kam lambi screen par chrome ko sikodo taki poora question + 4 options dikhein */
  @media (max-height: 760px), (max-width: 400px) {
    .qfix { padding: 10px 12px 0; }
    .timers { margin-top: 9px; gap: 8px; }
    .timer { padding: 7px 9px; gap: 8px; }
    .timer .ti { width: 28px; height: 28px; font-size: .82rem; }
    .timer .tv { font-size: 1rem; }
    .timer .tl { font-size: .64rem; }
    .qtext { font-size: .95rem; }
    .opts { gap: 7px; margin: 9px 0 10px; }
    .opt { padding: 9px 11px; gap: 11px; }
    .opt .k { width: 29px; height: 29px; font-size: .82rem; }
    .opt .v { font-size: .9rem; }
    .qmid { padding: 0 12px; }
    .qmid-in { padding: 6px 0 8px; }
    .qbot { padding: 8px 12px 10px; }
    .act-row { grid-template-columns: auto 1fr 1.7fr auto; gap: 7px; }
    .btn-sm { padding: 10px 7px; font-size: .83rem; white-space: nowrap; }
    .btn-ico { width: 40px; }
    .statusbar { margin-top: 7px; padding-top: 7px; }
    .btn-submit { padding: 7px 13px; }
  }

  /* ============ ALL QUESTIONS — print/PDF style sheet ============ */
  .btn-paper { display: flex; align-items: center; justify-content: center; gap: 9px; width: 100%; margin-top: 12px; padding: 12px; border-radius: 11px; background: var(--grad); color: #fff; font-weight: 600; font-size: .88rem; box-shadow: 0 5px 14px rgba(118,24,78,.26); }
  .paper { position: fixed; inset: 0; z-index: 120; background: #eef2f6; display: none; flex-direction: column; }
  .paper.show { display: flex; }
  .paper-bar { flex: none; background: var(--grad); color: #fff; display: flex; align-items: center; gap: 10px; padding: 10px 14px; box-shadow: 0 2px 12px rgba(76,21,101,.25); }
  .paper-bar b { flex: 1; min-width: 0; font-family: 'Oswald', sans-serif; font-size: 1rem; font-weight: 500; letter-spacing: 1.2px; text-transform: uppercase; }
  .paper-bar button { color: #fff; background: rgba(255,255,255,.14); border-radius: 10px; padding: 8px 13px; font-size: .82rem; font-weight: 600; display: flex; align-items: center; gap: 7px; }
  .paper-bar button:active { background: rgba(255,255,255,.26); }
  .paper-hint { flex: none; font-size: .72rem; opacity: .9; background: rgba(255,255,255,.14); padding: 6px 11px; border-radius: 99px; }
  @media (max-width: 420px) { .paper-hint { display: none; } }
  .paper-scroll { flex: 1 1 auto; min-height: 0; overflow-y: auto; padding: 14px 10px 30px; }

  .p-sheet { background: #fff; max-width: 860px; margin: 0 auto; border-radius: 10px; box-shadow: 0 8px 26px rgba(31,20,48,.12); padding: 14px; font-family: Tinos, "Times New Roman", serif; color: #1a1a1a; }
  .p-hdr { background: linear-gradient(180deg, #9a1f2f 0%, #8e1b2a 55%, #7d1524 100%); color: #fff; border-radius: 8px; padding: 13px 16px; display: flex; align-items: center; gap: 14px; box-shadow: 0 4px 12px rgba(110,18,32,.28); }
  .p-hdr .pl { width: 46px; height: 46px; flex: none; background: #fff; border-radius: 50%; padding: 3px; overflow: hidden; }
  .p-hdr .pt { flex: 1; min-width: 0; }
  .p-hdr h2 { font-size: 1.35rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase; line-height: 1.15; }
  .p-hdr small { display: block; font-size: .7rem; letter-spacing: 2px; text-transform: uppercase; opacity: .9; margin-top: 2px; }
  .p-hdr .pb { flex: none; background: rgba(0,0,0,.26); border: 1px solid rgba(255,255,255,.2); border-radius: 6px; padding: 6px 12px; font-size: .8rem; font-weight: 700; letter-spacing: .4px; text-transform: uppercase; white-space: nowrap; }
  .p-qs { margin-top: 12px; }
  .p-q { break-inside: avoid; page-break-inside: avoid; margin: 0 0 9px; }
  .p-qh { display: flex; gap: 8px; align-items: flex-start; }
  .p-badge { flex: none; background: #8e1b2a; color: #fff; font-weight: 700; font-size: .82rem; border-radius: 5px; padding: 4px 8px; min-width: 34px; text-align: center; line-height: 1.2; }
  .p-text { flex: 1; background: #fbecef; border-radius: 5px; padding: 5px 10px; font-weight: 700; font-size: .95rem; line-height: 1.35; }
  .p-opts { display: grid; grid-template-columns: 1fr 1fr; gap: 4px 12px; margin: 6px 0 0 42px; }
  .p-o { font-size: .9rem; line-height: 1.4; text-align: left; border: 1px solid transparent; border-radius: 5px; padding: 3px 7px; display: flex; gap: 6px; align-items: flex-start; font-family: inherit; color: inherit; }
  .p-o b { color: #8e1b2a; flex: none; }
  .p-o:hover { background: #fbecef; }
  .p-o.sel { background: #fbecef; border-color: #8e1b2a; font-weight: 700; }
  .p-o.sel b { color: #7d1524; }
  .p-q.answered .p-badge { background: #15803d; }
  .p-mark { font-size: .72rem; color: #15803d; font-weight: 700; margin-left: 42px; display: none; }
  .p-q.answered .p-mark { display: block; }
  .p-key { margin-top: 14px; border: 1px solid #8e1b2a; border-radius: 7px; padding: 10px 12px; }
  .p-key h3 { text-align: center; text-transform: uppercase; color: #8e1b2a; font-size: .95rem; letter-spacing: 1px; margin-bottom: 7px; }
  .p-key-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 3px 10px; font-size: .86rem; }
  .p-key-grid b { color: #8e1b2a; }
  .p-foot { margin-top: 14px; padding-top: 7px; border-top: 1px solid #8e1b2a; display: flex; justify-content: space-between; gap: 10px; font-size: .72rem; color: #6f6366; flex-wrap: wrap; }
  .p-foot b { color: #8e1b2a; }
  @media (max-width: 640px) { .p-opts { grid-template-columns: 1fr; margin-left: 8px; } .p-hdr h2 { font-size: 1.05rem; } .p-hdr .pb { display: none; } }

  /* math bits */
  .mi { font-family: Tinos, "Times New Roman", serif; font-size: 1.06em; font-style: italic; white-space: nowrap; }
  .mi .fn, .mi b { font-style: normal; }
  .frac { display: inline-flex; flex-direction: column; align-items: center; vertical-align: middle; line-height: 1.05; margin: 0 .12em; font-size: .92em; font-style: normal; }
  .frac > span { padding: 0 .22em; }
  .frac > span:first-child { border-bottom: 1px solid currentColor; }
  .rad { border-top: 1px solid currentColor; padding: 0 .12em; }
  .vec { position: relative; display: inline-block; font-style: italic; }
  .vec::before { content: "→"; position: absolute; left: 0; right: 0; top: -.72em; font-size: .64em; text-align: center; font-style: normal; }
  sup, sub { font-size: .72em; line-height: 0; }

  .toast {
    position: fixed; left: 50%; bottom: 24px; transform: translateX(-50%); z-index: 200;
    background: #0f172a; color: #fff; padding: 11px 18px; border-radius: 10px;
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
            <div class="qnum" id="qLabel">Q1</div>
            <div class="ch"><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></div>
            <div class="cnt"><span id="qNow">1</span><span> / <?= $QN ?></span></div>
            <button class="bmk" id="btnMark" title="Bookmark this question" aria-label="Bookmark"><i class="fa-regular fa-bookmark"></i></button>
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
        </div>

        <div class="qmid" id="qScroll">
          <div class="qmid-in">
            <div class="qtext" id="qText">—</div>
            <div id="qDead"></div>
            <div class="opts" id="qOpts"></div>
          </div>
        </div>

        <div class="qbot">
          <div class="act-row">
            <button class="btn-ico" id="btnPrev" title="Previous question" aria-label="Previous"><i class="fa fa-arrow-left"></i></button>
            <button class="btn-sm" id="btnClear"><i class="fa fa-eraser"></i> Clear</button>
            <button class="btn-sm grad" id="btnSaveNext">Save &amp; Next <i class="fa fa-arrow-right"></i></button>
            <button class="btn-ico" id="btnNext" title="Next question" aria-label="Next"><i class="fa fa-arrow-right"></i></button>
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
          <button class="btn-paper" id="btnPaper"><i class="fa fa-list-ul"></i> View all questions &amp; answer here</button>
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

<!-- ══════════════════ ALL QUESTIONS (paper view) ══════════════════ -->
<div class="paper" id="paperView">
  <div class="paper-bar">
    <button id="paperClose"><i class="fa fa-arrow-left"></i> Back</button>
    <b>All Questions</b>
    <span class="paper-hint" id="paperHint">Tap an option to answer</span>
  </div>
  <div class="paper-scroll" id="paperScroll">
    <div class="p-sheet">
      <div class="p-hdr">
        <div class="pl" data-logo></div>
        <div class="pt">
          <h2><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></h2>
          <small><?= htmlspecialchars($TEST['subtitle'], ENT_QUOTES) ?></small>
        </div>
        <div class="pb"><?= $QN ?> MCQ</div>
      </div>
      <div class="p-qs" id="paperQs"></div>
      <div class="p-key" id="paperKey" style="display:none">
        <h3>Answer Key</h3>
        <div class="p-key-grid" id="paperKeyGrid"></div>
      </div>
      <div class="p-foot">
        <span><b>Diploma Wallah</b> — Learn • Practice • Grow</span>
        <span><?= htmlspecialchars($TEST['title'], ENT_QUOTES) ?></span>
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
    <button class="btn-ghost" style="margin-top:9px" id="btnPaper2"><i class="fa fa-list-ul"></i> All questions with answer key</button>
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
  var LOGO_INLINE = "data:image/png;base64,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";
  var PER_Q = T.perQSec > 0;

  /* ---------- logo: file sharp hai, par file na mile to inline copy chale ---------- */
  (function () {
    function paintLogo(src) {
      [].forEach.call(document.querySelectorAll("[data-logo]"), function (h) {
        h.innerHTML = '<img class="brand-logo" src="' + src + '" alt="Diploma Wallah">';
      });
    }
    paintLogo(LOGO_INLINE);                       /* turant dikhao */
    var probe = new Image();
    probe.onload = function () { paintLogo("diplomawallah-logo.png"); };   /* file mili to HD */
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

  var curScreen = "scrStart";
  function paint(id) {
    curScreen = id;
    [].forEach.call(document.querySelectorAll(".screen"), function (s) { s.classList.toggle("show", s.id === id); });
    document.body.classList.toggle("mode-test", id === "scrTest");
    if (id !== "scrTest") window.scrollTo(0, 0);
  }
  /* har screen/overlay ko history me rakho taki phone ka Back app ke andar hi chale */
  function show(id, replace) {
    paint(id);
    var st2 = { scr: id, ov: null };
    try { replace ? history.replaceState(st2, "") : history.pushState(st2, ""); } catch (e) {}
  }
  function pushOverlay(name, replace) {
    try {
      var st2 = { scr: curScreen, ov: name };
      replace ? history.replaceState(st2, "") : history.pushState(st2, "");
    } catch (e) {}
  }
  window.addEventListener("popstate", function (e) {
    var s2 = e.state || { scr: "scrStart", ov: null };
    if (s2.scr !== curScreen) paint(s2.scr);
    setSheet(s2.ov === "sheet");
    setPaper(s2.ov === "paper");
  });
  try { history.replaceState({ scr: "scrStart", ov: null }, ""); } catch (e) {}

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
      /* time khatam hone par sirf batao — na aage bhejo, na option band karo */
      if (wasRunning && st.qt[st.i] <= 0) { renderQ(); toast("Time is up for this question."); }
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
    $("qLabel").textContent = "Q" + (i + 1);
    $("qText").innerHTML = mt(q.q);

    var over = PER_Q && st.qt[i] <= 0;
    $("qDead").innerHTML = over ? '<div class="timeover"><i class="fa fa-hourglass-end"></i> Time is up for this question — you can still answer it</div>' : "";

    var html = "";
    for (var k = 0; k < q.o.length; k++) {
      html += '<button class="opt' + (st.ans[i] === k ? " sel" : "") + '" data-k="' + k + '">' +
              '<span class="k">' + LETTERS[k] + '</span><span class="v"></span></button>';
    }
    $("qOpts").innerHTML = html;
    [].forEach.call($("qOpts").children, function (b, k) { b.querySelector(".v").innerHTML = mt(q.o[k]); });

    var mk = $("btnMark");
    mk.classList.toggle("on", !!st.mark[i]);
    mk.innerHTML = st.mark[i] ? '<i class="fa-solid fa-bookmark"></i>' : '<i class="fa-regular fa-bookmark"></i>';
    mk.title = st.mark[i] ? "Remove bookmark" : "Bookmark this question";

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
  function setSheet(on) { $("sheet").classList.toggle("show", !!on); $("scrim").classList.toggle("show", !!on); }
  function openSheet(tab) { paintPalette(); switchTab(tab); setSheet(true); pushOverlay("sheet"); }
  function closeSheet() {
    if (!$("sheet").classList.contains("show")) return;
    setSheet(false);
    if (history.state && history.state.ov === "sheet") history.back();
  }
  function switchTab(which) {
    [].forEach.call(document.querySelectorAll(".tab"), function (t) { t.classList.toggle("on", t.getAttribute("data-tab") === which); });
    $("paneP").classList.toggle("show", which === "pal");
    $("paneI").classList.toggle("show", which === "ins");
  }
  $("btnPalette").addEventListener("click", function () { openSheet("pal"); });
  $("btnInstr").addEventListener("click", function () { openSheet("ins"); });
  $("btnSheetClose").addEventListener("click", closeSheet);
  $("scrim").addEventListener("click", closeSheet);
  document.addEventListener("keydown", function (e) { if (e.key === "Escape") { closePaper(); closeSheet(); } });
  [].forEach.call(document.querySelectorAll(".tab"), function (t) {
    t.addEventListener("click", function () { switchTab(t.getAttribute("data-tab")); });
  });
  $("palGrid").addEventListener("click", function (e) {
    var b = e.target.closest(".pal-btn"); if (!b) return;
    closeSheet(); go(parseInt(b.getAttribute("data-i"), 10));
  });

  /* ---------- all questions (paper view) ---------- */
  function buildPaper() {
    var h = "", i, k;
    for (i = 0; i < T.count; i++) {
      var q = T.questions[i];
      var picked = st && st.ans ? st.ans[i] : null;
      h += '<div class="p-q' + (picked !== null && picked !== undefined ? " answered" : "") + '" data-q="' + i + '">' +
           '<div class="p-qh"><span class="p-badge">Q' + (i + 1) + '</span>' +
           '<span class="p-text">' + mt(q.q) + "</span></div><div class=\"p-opts\">";
      for (k = 0; k < q.o.length; k++) {
        h += '<button type="button" class="p-o' + (picked === k ? " sel" : "") + '" data-q="' + i + '" data-k="' + k + '">' +
             "<b>(" + LETTERS[k].toLowerCase() + ")</b> <span>" + mt(q.o[k]) + "</span></button>";
      }
      h += '</div><div class="p-mark"><i class="fa fa-circle-check"></i> Answered</div></div>';
    }
    $("paperQs").innerHTML = h;

    /* answer key sirf test khatam hone ke baad */
    var done = !!(st && st.done);
    $("paperKey").style.display = done ? "" : "none";
    if (done) {
      var g = "";
      for (i = 0; i < T.count; i++) g += "<span>" + (i + 1) + " &ndash; <b>" + LETTERS[T.questions[i].a] + "</b></span>";
      $("paperKeyGrid").innerHTML = g;
    }
  }
  function setPaper(on) { $("paperView").classList.toggle("show", !!on); }
  function openPaper() {
    buildPaper(); setPaper(true); $("paperScroll").scrollTop = 0;
    /* palette se khula hai to usi entry ko replace karo — Back seedha test par le jaye */
    pushOverlay("paper", !!(history.state && history.state.ov === "sheet"));
  }
  function closePaper() {
    if (!$("paperView").classList.contains("show")) return;
    setPaper(false);
    if (st && !st.done) renderQ();
    if (history.state && history.state.ov === "paper") history.back();
  }
  $("btnPaper").addEventListener("click", function () { setSheet(false); openPaper(); });
  $("btnPaper2").addEventListener("click", function () { openPaper(); });
  $("paperClose").addEventListener("click", closePaper);
  /* paper view se answer karo — wahi answer MCQ test me bhi lag jata hai */
  $("paperQs").addEventListener("click", function (e) {
    var b = e.target.closest(".p-o"); if (!b || !st || st.done) return;
    var qi = parseInt(b.getAttribute("data-q"), 10), k = parseInt(b.getAttribute("data-k"), 10);
    st.ans[qi] = k; save();
    var box = b.closest(".p-q");
    [].forEach.call(box.querySelectorAll(".p-o"), function (o) { o.classList.toggle("sel", o === b); });
    box.classList.add("answered");
    renderQ();                      /* test screen, palette aur counts sync */
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
    setSheet(false); setPaper(false); clearSaved();

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
    show("scrResult", true);
  }
  function buildReview() {
    var h = "";
    for (var i = 0; i < T.count; i++) {
      var q = T.questions[i], you = st.ans[i];
      var cls = you === null ? "sk" : (you === q.a ? "ok" : "no");
      h += '<div class="rev-q ' + cls + '">' +
             '<div class="rq"><span>Q' + (i + 1) + '.</span> ' + mt(q.q) + "</div>" +
             '<div class="rev-a you' + (you !== null && you === q.a ? " ok" : "") + '"><span class="tag">' +
               (you === null ? "Not answered" : "Your answer") + '</span><span>' +
               (you === null ? "—" : LETTERS[you] + ") " + mt(q.o[you])) + "</span></div>" +
             (you === q.a ? "" :
               '<div class="rev-a cor"><span class="tag">Correct</span><span>' + LETTERS[q.a] + ") " + mt(q.o[q.a]) + "</span></div>") +
             (q.e ? '<div class="rev-exp"><b>Explanation:</b> ' + mt(q.e) + "</div>" : "") +
           "</div>";
    }
    $("revBox").innerHTML = h;
  }
  function esc(s) {
    return String(s == null ? "" : s).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
  }

  /* =====================================================================
     MATH — $...$ ke andar LaTeX-lite, bahar simple ^ / _ aur chemistry
  ===================================================================== */
  var GREEK = { alpha:"α", beta:"β", gamma:"γ", Gamma:"Γ", delta:"δ", Delta:"Δ", epsilon:"ε", varepsilon:"ε",
    zeta:"ζ", eta:"η", theta:"θ", Theta:"Θ", iota:"ι", kappa:"κ", lambda:"λ", Lambda:"Λ", mu:"μ", nu:"ν",
    xi:"ξ", pi:"π", Pi:"Π", rho:"ρ", sigma:"σ", Sigma:"Σ", tau:"τ", upsilon:"υ", phi:"φ", varphi:"φ", Phi:"Φ",
    chi:"χ", psi:"ψ", Psi:"Ψ", omega:"ω", Omega:"Ω", hbar:"ħ", infty:"∞", angstrom:"Å" };
  var TEX = { times:"×", cdot:"·", div:"÷", pm:"±", mp:"∓", ge:"≥", geq:"≥", le:"≤", leq:"≤", ne:"≠", neq:"≠",
    approx:"≈", propto:"∝", equiv:"≡", sim:"∼", to:"→", rightarrow:"→", leftarrow:"←", leftrightarrow:"↔",
    Rightarrow:"⇒", Leftrightarrow:"⇔", degree:"°", circ:"°", partial:"∂", nabla:"∇", sum:"Σ", prod:"∏",
    int:"∫", oint:"∮", sqrtsym:"√", therefore:"∴", because:"∵", ldots:"…", cdots:"⋯", in:"∈", notin:"∉",
    perp:"⊥", parallel:"∥", angle:"∠", ell:"ℓ", prime:"′", AA:"Å" };
  var FUNCS = "sin|cos|tan|cot|sec|cosec|csc|log|ln|exp|lim|max|min|arcsin|arccos|arctan|sinh|cosh|tanh";

  function mathify(t) {
    var x = t;
    x = x.replace(/\\left\s*|\\right\s*/g, "").replace(/\\,|\\;|\\!|\\ /g, " ");
    x = x.replace(/\\(?:text|mathrm|operatorname)\{([^{}]*)\}/g, "$1")
         .replace(/\\(?:mathbf|boldsymbol)\{([^{}]*)\}/g, "<b>$1</b>");
    for (var i = 0; i < 4; i++) x = x.replace(/\\frac\{([^{}]*)\}\{([^{}]*)\}/g, '<span class="frac"><span>$1</span><span>$2</span></span>');
    x = x.replace(/\\sqrt\{([^{}]*)\}/g, '√<span class="rad">$1</span>').replace(/\\sqrt\s*([A-Za-z0-9]+)/g, '√<span class="rad">$1</span>');
    x = x.replace(new RegExp("\\\\(" + FUNCS + ")(?![A-Za-z])", "g"), '<span class="fn">$1</span>&thinsp;');
    x = x.replace(/\\vec\{([^{}]*)\}/g, '<span class="vec">$1</span>').replace(/\\vec\s*([A-Za-z])/g, '<span class="vec">$1</span>');
    x = x.replace(/\\([A-Za-z]+)/g, function (m, w) {
      return TEX.hasOwnProperty(w) ? TEX[w] : (GREEK.hasOwnProperty(w) ? GREEK[w] : m);
    });
    x = x.replace(/\^\{([^{}]*)\}/g, "<sup>$1</sup>").replace(/_\{([^{}]*)\}/g, "<sub>$1</sub>");
    x = x.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>").replace(/\^(-?[0-9A-Za-z]+)/g, "<sup>$1</sup>");
    x = x.replace(/([^\s_^{}<>;&])_(-?[0-9A-Za-z]+)/g, "$1<sub>$2</sub>");
    x = x.replace(/\{|\}/g, "");
    return x;
  }
  /** plain text ke liye halka pass: H2O, 10^-27, Ca^2+ */
  function smartText(t) {
    return t.replace(/\^\(([^)]+)\)/g, "<sup>$1</sup>")
            .replace(/\^(-?\d+(?:\.\d+)?|[+-]|\d*[+-])/g, "<sup>$1</sup>")
            .replace(/([A-Za-z\)])_(\d+)/g, "$1<sub>$2</sub>")
            .replace(/\b(\d+(?:\.\d+)?)\s*x\s*10/g, "$1 × 10");
  }
  /** ye sab jagah use hota hai: question, option, review, paper view */
  function mt(raw) {
    var t = esc(raw), out = "", i = 0;
    while (true) {
      var a = t.indexOf("$", i);
      if (a < 0) { out += smartText(t.slice(i)); break; }
      var b = t.indexOf("$", a + 1);
      if (b < 0) { out += smartText(t.slice(i)); break; }
      out += smartText(t.slice(i, a)) + '<span class="mi">' + mathify(t.slice(a + 1, b)) + "</span>";
      i = b + 1;
    }
    return out;
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
