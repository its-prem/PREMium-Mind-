/**
 * Mock test series. To add a test, add an object here.
 * answer = index (0-based) of the correct option.
 */
export const MOCK_TESTS = [
  {
    id: 'computer-basics',
    title: 'Computer Fundamentals',
    icon: 'desktop-outline',
    tint: '#38bdf8',
    minutes: 8,
    questions: [
      { q: 'Which part of the computer is called its "brain"?', options: ['RAM', 'CPU', 'Hard disk', 'Monitor'], answer: 1, why: 'The CPU executes instructions and controls the computer.' },
      { q: 'Which memory loses its data when power is switched off?', options: ['ROM', 'SSD', 'RAM', 'Hard disk'], answer: 2, why: 'RAM is volatile memory.' },
      { q: 'The binary equivalent of decimal 10 is:', options: ['1010', '1100', '1001', '1110'], answer: 0, why: '8 + 2 = 10, so 1010.' },
      { q: 'Which of these is an operating system?', options: ['Oracle', 'Linux', 'Chrome', 'Python'], answer: 1, why: 'Linux is an OS; the others are a database, a browser and a language.' },
      { q: 'One byte is equal to how many bits?', options: ['4', '8', '16', '32'], answer: 1, why: '1 byte = 8 bits.' },
      { q: 'Which device is an input device?', options: ['Printer', 'Speaker', 'Scanner', 'Projector'], answer: 2, why: 'A scanner sends data into the computer.' },
      { q: 'HTTP stands for:', options: ['HyperText Transfer Protocol', 'High Tech Transfer Program', 'HyperText Transmission Process', 'Home Tool Transfer Protocol'], answer: 0, why: 'HTTP = HyperText Transfer Protocol.' },
      { q: 'Which of these is the fastest memory, placed closest to the CPU?', options: ['Pen drive', 'Register', 'DVD', 'Floppy'], answer: 1, why: 'Registers (then cache) are the fastest memory.' },
    ],
  },
  {
    id: 'programming-basics',
    title: 'Programming Basics (C / Python)',
    icon: 'code-slash-outline',
    tint: '#a78bfa',
    minutes: 10,
    questions: [
      { q: 'Which symbol ends a statement in C?', options: [':', ';', '.', ','], answer: 1, why: 'C statements end with a semicolon.' },
      { q: 'What is the output of print(2 ** 3) in Python?', options: ['6', '5', '8', '9'], answer: 2, why: '** is the power operator: 2 to the power 3 = 8.' },
      { q: 'Which loop checks its condition before running the body?', options: ['do-while', 'while', 'Both', 'None'], answer: 1, why: 'A while loop tests first; do-while runs once before testing.' },
      { q: 'Array indexing in C starts from:', options: ['0', '1', '-1', 'Any number'], answer: 0, why: 'The first element is at index 0.' },
      { q: 'Which is NOT a valid variable name in Python?', options: ['_total', 'total2', '2total', 'Total'], answer: 2, why: 'A name cannot start with a digit.' },
      { q: 'Which data type stores decimal numbers in C?', options: ['int', 'char', 'float', 'void'], answer: 2, why: 'float (and double) store fractional values.' },
      { q: 'What does len([4, 5, 6]) return in Python?', options: ['2', '3', '4', '15'], answer: 1, why: 'The list has 3 elements.' },
      { q: 'The worst-case time complexity of linear search is:', options: ['O(1)', 'O(log n)', 'O(n)', 'O(n^2)'], answer: 2, why: 'It may check every element once.' },
      { q: 'A function that calls itself is called:', options: ['Iteration', 'Recursion', 'Overloading', 'Inheritance'], answer: 1, why: 'That is the definition of recursion.' },
      { q: 'Which keyword defines a function in Python?', options: ['func', 'define', 'def', 'function'], answer: 2, why: 'Python uses def.' },
    ],
  },
  {
    id: 'electrical-basics',
    title: 'Basic Electrical & Electronics',
    icon: 'flash-outline',
    tint: '#fbbf24',
    minutes: 8,
    questions: [
      { q: "Ohm's law is:", options: ['V = I x R', 'V = I / R', 'I = V x R', 'R = V x I'], answer: 0, why: 'Voltage equals current times resistance.' },
      { q: 'The unit of electrical resistance is:', options: ['Volt', 'Ampere', 'Ohm', 'Watt'], answer: 2, why: 'Resistance is measured in ohms.' },
      { q: 'In a series circuit, which quantity is the same through every component?', options: ['Voltage', 'Current', 'Power', 'Resistance'], answer: 1, why: 'There is only one path, so the same current flows everywhere.' },
      { q: 'Which device converts AC to DC?', options: ['Transformer', 'Rectifier', 'Inverter', 'Capacitor'], answer: 1, why: 'A rectifier converts alternating current to direct current.' },
      { q: 'A transformer works on the principle of:', options: ['Self induction', 'Mutual induction', 'Photoelectric effect', 'Hall effect'], answer: 1, why: 'Two coils are linked by changing magnetic flux (mutual induction).' },
      { q: 'Which of these is a semiconductor material?', options: ['Copper', 'Silicon', 'Aluminium', 'Silver'], answer: 1, why: 'Silicon and germanium are semiconductors.' },
      { q: 'Electrical power in watts equals:', options: ['V x I', 'V / I', 'I / V', 'V + I'], answer: 0, why: 'P = V x I.' },
      { q: 'A diode allows current to flow:', options: ['In both directions', 'In one direction only', 'Never', 'Only at high temperature'], answer: 1, why: 'It conducts in forward bias only.' },
    ],
  },
];
