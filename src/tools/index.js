/**
 * Registry of the Free Tools. Each tool lives in its own file in this folder.
 * To add a tool: create <Name>Tool.jsx here, import it, and add one line below.
 * To remove a tool: delete its line (and its file).
 */
import CgpaTool from './CgpaTool';
import MergeTool from './MergeTool';
import SplitTool from './SplitTool';
import CompressTool from './CompressTool';
import WatermarkTool from './WatermarkTool';
import ImageToPdfTool from './ImageToPdfTool';
import ImageResizeTool from './ImageResizeTool';

export const TOOLS = [
  { id: 'cgpa', title: 'CGPA / SGPA Calculator', icon: 'calculator-outline', tint: '#34d399', sub: 'Marks to SGPA, CGPA and percentage', Component: CgpaTool },
  { id: 'merge', title: 'PDF Merge', icon: 'documents-outline', tint: '#38bdf8', sub: 'Join many PDFs into one file', Component: MergeTool },
  { id: 'split', title: 'PDF Split', icon: 'cut-outline', tint: '#f472b6', sub: 'Many page ranges, each as its own PDF', Component: SplitTool },
  { id: 'compress', title: 'PDF Compress', icon: 'contract-outline', tint: '#a78bfa', sub: 'Make a PDF the size you want', Component: CompressTool },
  { id: 'watermark', title: 'PDF Watermark', icon: 'water-outline', tint: '#fb7185', sub: 'Stamp text on every page', Component: WatermarkTool },
  { id: 'img2pdf', title: 'Image to PDF', icon: 'images-outline', tint: '#2dd4bf', sub: 'Photos and scans into one PDF', Component: ImageToPdfTool },
  { id: 'image', title: 'Image Resize', icon: 'image-outline', tint: '#fbbf24', sub: 'Resize, crop and set file size', Component: ImageResizeTool },
];
