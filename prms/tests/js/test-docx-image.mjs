/**
 * Node.js test: verify docx v9 ImageRun works end-to-end.
 * Run with: node tests/js/test-docx-image.mjs
 */
import { Document, Packer, Paragraph, TextRun, ImageRun } from '../../node_modules/docx/dist/index.mjs'
import { writeFileSync, readFileSync, existsSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))
const root = join(__dirname, '../../')

// Build a minimal 1×1 white PNG as a Buffer (no file dependency)
// PNG signature + IHDR + IDAT + IEND — a valid 1x1 white pixel PNG
const PNG_1x1 = Buffer.from(
  '89504e470d0a1a0a' +                         // PNG signature
  '0000000d49484452' + '00000001' + '00000001' + '08020000' + '0090wc3d' + // IHDR (placeholder, we'll use real)
  '0000000c4944415478' + '9c6260f8cf' + '00000002' + '00012721e2' + // IDAT
  '0000000049454e44ae426082',                   // IEND
  'hex'
)

// Use a real minimal valid PNG (base64 encoded 1x1 red pixel)
const REAL_PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwADhQGAWjR9awAAAABJRU5ErkJggg=='
const pngBuffer = Buffer.from(REAL_PNG_BASE64, 'base64')

console.log('PNG buffer size:', pngBuffer.length, 'bytes')

// Test 1: Basic ImageRun with ArrayBuffer
console.log('\n--- Test 1: ImageRun with ArrayBuffer ---')
try {
  const arrayBuf = pngBuffer.buffer.slice(pngBuffer.byteOffset, pngBuffer.byteOffset + pngBuffer.byteLength)
  const doc = new Document({
    sections: [{
      children: [
        new Paragraph({ children: [new TextRun('Before image')] }),
        new Paragraph({
          children: [new ImageRun({
            type: 'png',
            data: arrayBuf,
            transformation: { width: 100, height: 100 },
          })]
        }),
        new Paragraph({ children: [new TextRun('After image')] }),
      ]
    }]
  })
  const buf = await Packer.toBuffer(doc)
  const outPath = join(root, 'public/test-image-export.docx')
  writeFileSync(outPath, buf)
  console.log('✅ SUCCESS — wrote', buf.length, 'bytes to', outPath)
} catch (e) {
  console.error('❌ FAILED:', e.message)
  console.error(e)
}

// Test 2: ImageRun with Buffer directly
console.log('\n--- Test 2: ImageRun with Buffer ---')
try {
  const doc = new Document({
    sections: [{
      children: [
        new Paragraph({
          children: [new ImageRun({
            type: 'png',
            data: pngBuffer,
            transformation: { width: 200, height: 200 },
          })]
        }),
      ]
    }]
  })
  const buf = await Packer.toBuffer(doc)
  console.log('✅ SUCCESS — ImageRun with Buffer:', buf.length, 'bytes')
} catch (e) {
  console.error('❌ FAILED:', e.message)
}

// Test 3: Multiple images in one document
console.log('\n--- Test 3: Multiple images ---')
try {
  const doc = new Document({
    sections: [{
      children: [
        new Paragraph({ children: [new TextRun('Document with multiple images:')] }),
        new Paragraph({
          children: [new ImageRun({ type: 'png', data: pngBuffer, transformation: { width: 100, height: 100 } })]
        }),
        new Paragraph({ children: [new TextRun('Between images')] }),
        new Paragraph({
          children: [new ImageRun({ type: 'png', data: pngBuffer, transformation: { width: 150, height: 150 } })]
        }),
      ]
    }]
  })
  const buf = await Packer.toBuffer(doc)
  console.log('✅ SUCCESS — Multiple images:', buf.length, 'bytes')
} catch (e) {
  console.error('❌ FAILED:', e.message)
}

console.log('\nAll tests complete.')
