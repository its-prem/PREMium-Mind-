/**
 * Diploma syllabus: semesters 1-6 -> subjects -> units -> topics.
 *
 * To add a subject: add it to SUBJECTS below and set
 *     sem:      1 to 6                      the semester it belongs to (it shows inside that semester)
 *     branches: 'all'  or  ['cse', 'ece']   'all' = common to every branch, or the ids of the branches it is for
 * A unit is { title, sections: [{ heading?, topics: [text, ...] }] }.
 * Every topic is a separate line the student can tick as completed.
 */

export const BRANCHES = [
  { id: 'cse', title: 'Computer Science', desc: 'Notes, Question Banks, Lab Manuals & Resources.', icon: 'laptop-outline', tint: '#38bdf8' },
  { id: 'mechanical', title: 'Mechanical Engg', desc: 'Thermodynamics, TOM, SOM, and Workshop Technology.', icon: 'cog-outline', tint: '#fb923c' },
  { id: 'civil', title: 'Civil Engineering', desc: 'Surveying, Construction Materials, and Concrete Tech.', icon: 'business-outline', tint: '#a78bfa' },
  { id: 'electrical', title: 'Electrical Engg', desc: 'Power Systems, Machines, and Circuit Theory.', icon: 'flash-outline', tint: '#fbbf24' },
  { id: 'ece', title: 'Electronics (ECE)', desc: 'Analog & Digital Circuits, Communication Systems.', icon: 'hardware-chip-outline', tint: '#34d399' },
  { id: 'electronics', title: 'Electronics Engg', desc: 'Core Electronics resources and term works.', icon: 'radio-outline', tint: '#2dd4bf' },
  { id: 'automobile', title: 'Automobile Engg', desc: 'Vehicle Dynamics, Engine Systems and Safety.', icon: 'car-sport-outline', tint: '#f472b6' },
  { id: 'mining', title: 'Mining Engg', desc: 'Geology, Mine Surveying, and Machinery.', icon: 'diamond-outline', tint: '#94a3b8' },
  { id: 'metallurgy', title: 'Metallurgy', desc: 'Material Science and metal processing notes.', icon: 'flame-outline', tint: '#f87171' },
];

export const SUBJECTS = {
  chemistry: {
    id: 'chemistry',
    title: 'Engineering Chemistry',
    semester: '1st Semester (common to all branches)',
    sem: 1,
    branches: 'all',
    code: '',
    icon: 'flask-outline',
    units: [
      {
        title: 'Atomic Structure',
        sections: [
          {
            topics: [
              'Definition of Atom; Fundamental Particles of Atom – their Mass, Charge, Location',
              'Definition of Atomic no., Atomic Mass no.',
              'Isotopes & Isobars, and their distinction with suitable examples',
              'Bohr’s Theory, Definition',
              'Shape of the orbitals & distinction between Orbits & Orbitals',
              'Hund’s Rule',
              'Filling up of the Orbitals by Aufbau’s Principle (till Atomic no. 30)',
              'Definition & types of valency (Electrovalency & Covalency)',
              'Octet Rule, Duplet Rule',
              'Formation of Electrovalent & Covalent Compounds e.g. NaCl, CaCl2, MgO, AlCl3, CO2, H2O, Cl2, NH3, C2H4, N2, C2H2',
              'Distinction between electrovalent & covalent compounds',
            ],
          },
        ],
      },
      {
        title: 'Electrochemistry',
        sections: [
          {
            topics: [
              'Definition & differentiation of Atom, Ion',
              'Definition of Ionisation & Electrolytic dissociation',
              'Arrhenius Theory of Ionisation',
              'Degree of Ionisation & factors affecting degree of ionization',
              'Significance of the terms involved in Electrolysis – Conductors, Insulators, Dielectrics, Electrolyte, Non Electrolyte, Electrolysis, Electrolytic Cell, Electrodes',
              'Mechanism of Electrolysis – Primary & Secondary Reactions at Cathode & Anode',
              'Concept of electrode potential such as reduction potential & oxidation potential',
              'Electrochemical Series for Cations & Anions',
              'Electrolysis of CuSO4 Solution by using Cu Electrode & Platinum Electrode',
              'Electrolysis of NaCl solution & fused NaCl by using carbon electrode',
              'Faraday’s first & second law of Electrolysis & Numericals',
              'Electrochemical Cells & Batteries – Definition, types such as Primary & Secondary Cells & their examples',
              'Construction, Working & Applications of Dry Cell & Lead – Acid Storage Cell',
              'Applications of Electrolysis such as Electroplating & Electro refining, Electrometallurgy & Electrotyping',
            ],
          },
        ],
      },
      {
        title: 'Metals & Alloys',
        sections: [
          {
            heading: '1. Metals',
            topics: [
              'Occurrence of Metals',
              'Definition of Metallurgy, Mineral, Ore, Gangue, Flux & Slag',
              'Mechanical Properties of metals such as Hardness, Toughness, Ductility, Malleability, Tensile strength, Machinability, Weldability, Forging, Soldering, Castability',
              'Stages of Extraction of Metals from its Ores in detail i.e. Crushing, Concentration, Reduction, Refining',
              'Physical Properties & Applications of some commonly used metals such as Fe, Cu, Al, Cr, Ni, Sn, Pb, Zn, Co, Ag, W',
            ],
          },
          {
            heading: '2. Alloys',
            topics: [
              'Definition of Alloy, Purposes of Making alloy',
              'Preparation Methods',
              'Classification of Alloys such as Ferrous & Non Ferrous & their examples',
              'Composition, Properties & Applications of Alnico, Duralumin, Dutch Metal, German Silver / Nickel Silver, Gun Metal, Monel metal, Wood’s Metal, Babbitt metal',
            ],
          },
        ],
      },
      {
        title: 'Non Metallic Materials',
        sections: [
          {
            heading: '1. Plastics',
            topics: [
              'Definition of Plastic',
              'Formation of Plastic by Addition & Condensation Polymerisation by giving e.g. of Polyethylene & Bakelite plastic respectively',
              'Types of Plastic – Thermosoftening & Thermosetting Plastic, with Definition, Distinction & e.g.',
              'Compounding of Plastics – Resins, Fillers, Plasticizers, Accelerators, Pigments & their examples',
              'Engineering Applications of Plastic based on their properties',
            ],
          },
          {
            heading: '2. Rubber',
            topics: [
              'Natural Rubber: Its Processing, Drawbacks of Natural Rubber, Vulcanisation of Rubber with Chemical Reaction',
              'Synthetic Rubber: Definition, & e.g., Distinction between natural & synthetic rubber',
              'Properties of rubber such as elasticity, tack, abrasion resistant, stress & strain and related engg. application',
            ],
          },
        ],
      },
    ],
  },
};

export const SEMESTERS = [1, 2, 3, 4, 5, 6];
export const semesterName = (n) => ['1st', '2nd', '3rd', '4th', '5th', '6th'][n - 1] + ' Semester';

/** The subjects shown inside one semester. With a branch: the common ones plus that branch's own. Without: all of them. */
export function subjectsOfSemester(sem, branchId) {
  return Object.values(SUBJECTS).filter((s) => {
    if (s.sem !== sem) return false;
    if (!branchId || s.branches === 'all') return true;
    return Array.isArray(s.branches) && s.branches.includes(branchId);
  });
}

export const getBranch = (id) => BRANCHES.find((b) => b.id === id);
export const getSubject = (id) => SUBJECTS[id];

/** Every topic of a subject as { id, unit, section } so progress can be counted. */
export function listTopics(subject) {
  const out = [];
  subject.units.forEach((u, ui) =>
    u.sections.forEach((s, si) =>
      s.topics.forEach((t, ti) => out.push({ id: `${ui}-${si}-${ti}`, unit: ui, section: si }))
    )
  );
  return out;
}
