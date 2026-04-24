import { Editor, Mark, Extension, mergeAttributes } from '@tiptap/core'
import StarterKit from '@tiptap/starter-kit'
import Collaboration from '@tiptap/extension-collaboration'
import CollaborationCursor from '@tiptap/extension-collaboration-cursor'
import Placeholder from '@tiptap/extension-placeholder'
import Typography from '@tiptap/extension-typography'
import TextStyle from '@tiptap/extension-text-style'
import FontFamily from '@tiptap/extension-font-family'
import Underline from '@tiptap/extension-underline'
import Image from '@tiptap/extension-image'
import Table from '@tiptap/extension-table'
import TableRow from '@tiptap/extension-table-row'
import TableHeader from '@tiptap/extension-table-header'
import TableCell from '@tiptap/extension-table-cell'
import TextAlign from '@tiptap/extension-text-align'
import * as Y from 'yjs'

import { HocuspocusProvider } from '@hocuspocus/provider'
import {
  Document,
  Packer,
  Paragraph,
  TextRun,
  HeadingLevel,
  AlignmentType,
  ImageRun,
  Table as DocxTable,
  TableRow as DocxTableRow,
  TableCell as DocxTableCell,
  WidthType,
} from 'docx'
import { saveAs } from 'file-saver'

// Determine Hocuspocus WS URL
const WS_URL = window.HOCUSPOCUS_URL || `ws://${window.location.hostname}:1234`

// Custom FontSize extension — adds fontSize attribute to the TextStyle mark
const FontSize = Extension.create({
  name: 'fontSize',
  addOptions() { return { types: ['textStyle'] } },
  addGlobalAttributes() {
    return [{
      types: this.options.types,
      attributes: {
        fontSize: {
          default: null,
          parseHTML: el => el.style.fontSize?.replace('pt', '') || null,
          renderHTML: attrs => attrs.fontSize
            ? { style: `font-size: ${attrs.fontSize}pt` } : {},
        },
      },
    }]
  },
  addCommands() {
    return {
      setFontSize:   size => ({ chain }) => chain().setMark('textStyle', { fontSize: size }).run(),
      unsetFontSize: ()   => ({ chain }) => chain().setMark('textStyle', { fontSize: null }).removeEmptyTextStyle().run(),
    }
  },
})

// Custom Indent extension — adds data-indent attribute to block nodes
const MAX_INDENT = 5
const Indent = Extension.create({
  name: 'indent',
  addGlobalAttributes() {
    return [{
      types: ['paragraph', 'heading', 'listItem'],
      attributes: {
        indent: {
          default: 0,
          parseHTML: el => parseInt(el.getAttribute('data-indent') || '0', 10),
          renderHTML: attrs => attrs.indent > 0 ? { 'data-indent': attrs.indent } : {},
        },
      },
    }]
  },
  addCommands() {
    return {
      indent: () => ({ tr, state, dispatch }) => {
        const { selection } = state
        const { from, to } = selection
        state.doc.nodesBetween(from, to, (node, pos) => {
          if (['paragraph', 'heading', 'listItem'].includes(node.type.name)) {
            const current = node.attrs.indent || 0
            if (current < MAX_INDENT && dispatch) {
              tr.setNodeMarkup(pos, null, { ...node.attrs, indent: current + 1 })
            }
          }
        })
        if (dispatch) dispatch(tr)
        return true
      },
      outdent: () => ({ tr, state, dispatch }) => {
        const { selection } = state
        const { from, to } = selection
        state.doc.nodesBetween(from, to, (node, pos) => {
          if (['paragraph', 'heading', 'listItem'].includes(node.type.name)) {
            const current = node.attrs.indent || 0
            if (current > 0 && dispatch) {
              tr.setNodeMarkup(pos, null, { ...node.attrs, indent: current - 1 })
            }
          }
        })
        if (dispatch) dispatch(tr)
        return true
      },
    }
  },
})

// TipTap mark that wraps commented text with a data-comment-id attribute.
// Stored in the Yjs document so all collaborators see the highlight.
const InlineComment = Mark.create({
  name: 'inlineComment',
  excludes: '',
  inclusive: false,
  addAttributes() {
    return {
      commentId: {
        default: null,
        parseHTML: el => el.getAttribute('data-comment-id'),
        renderHTML: attrs => ({ 'data-comment-id': attrs.commentId }),
      },
    }
  },
  parseHTML() {
    return [{ tag: 'span[data-comment-id]' }]
  },
  renderHTML({ HTMLAttributes }) {
    return ['span', mergeAttributes(HTMLAttributes, { class: 'te-comment-highlight' }), 0]
  },
})

function generateUUID() {
  return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, c => {
    const r = Math.random() * 16 | 0
    return (c === 'x' ? r : (r & 0x3 | 0x8)).toString(16)
  })
}

class TextEditorInstance {
  constructor(container) {
    this.container      = container
    this.recordId       = container.dataset.record
    this.fieldSlug      = container.dataset.field
    this.token          = container.dataset.token
    this.template       = container.dataset.template || ''
    this.requireReview  = container.dataset.requireReview === '1'
    this.logHistory     = container.dataset.logHistory === '1'
    this.userName       = container.dataset.userName || 'Anonymous'
    this.userColor      = container.dataset.userColor || '#6366f1'
    this.readonly       = container.dataset.readonly === '1'
    this.hiddenInput    = document.getElementById(`te-input-${this.fieldSlug}`)
    this.initialContent = container._teContent || container._teTemplate || ''
    this.debounceTimer  = null
    this.lastContent    = ''
    this.margins        = { top: 20, right: 20, bottom: 20, left: 20 }
    this.pageSize       = 'a4'

    // Expose instance on container so external code (commit hook, buttons) can sync
    container._teInstance = this

    this.init()
  }

  init() {
    this.buildShell()

    const isNew = this.recordId === 'new'
    const docName = isNew ? null : `record-${this.recordId}-field-${this.fieldSlug}`

    this.ydoc = new Y.Doc()
    this._remoteUpdate = false

    if (!isNew) {
      this.provider = new HocuspocusProvider({
        url: WS_URL,
        name: docName,
        document: this.ydoc,
        token: this.token,
        onConnect:    () => this.updateStatus('connected'),
        onDisconnect: () => this.updateStatus('disconnected'),
        onSynced:     () => {
          this.updateStatus('synced')
          // If Hocuspocus returned an empty doc (never synced before — e.g. record
          // was created via the new-record form which has no Hocuspocus connection),
          // seed the Yjs doc from the DB value / template so all future opens work.
          const editorIsBlank = this.editor.getText().trim() === ''
          if (editorIsBlank && this.initialContent) {
            this._remoteUpdate = true
            this.editor.commands.setContent(this.initialContent)
            Promise.resolve().then(() => { this._remoteUpdate = false })
          }
        },
      })

      this.ydoc.on('update', (update, origin) => {
        if (origin === this.provider) {
          this._remoteUpdate = true
          Promise.resolve().then(() => { this._remoteUpdate = false })
        }
      })
    }

    const extensions = [
      StarterKit.configure({ history: false }),
      Indent,
      Typography,
      Placeholder.configure({ placeholder: 'Start typing…' }),
      InlineComment,
      TextStyle,
      FontFamily,
      FontSize,
      Underline,
      Image.configure({ inline: true, allowBase64: true }),
      Table.configure({ resizable: true }),
      TableRow,
      TableHeader,
      TableCell,
      TextAlign.configure({ types: ['heading', 'paragraph'] }),
    ]

    if (!isNew && this.provider) {
      extensions.push(
        Collaboration.configure({ document: this.ydoc }),
        CollaborationCursor.configure({
          provider: this.provider,
          user: { name: this.userName, color: this.userColor },
        })
      )
    }

    this.editor = new Editor({
      element: this.editorEl,
      extensions,
      editable: !this.readonly,
      content: isNew ? this.initialContent : '',
      onCreate: ({ editor }) => {
        this.lastContent = editor.getText()
        if (isNew && this.initialContent) {
          this.syncToLivewire(editor.getHTML())
        }
        requestAnimationFrame(() => { this.applyPageSize(); this.updateLineNumbers() })
      },
      onUpdate: ({ editor }) => {
        const html = editor.getHTML()
        this.syncToLivewire(html)
        this.updateLineNumbers()
        if (this.logHistory && !isNew && !this._remoteUpdate) {
          this.logChange(editor)
        }
      },
    })

    this.setupToolbar()
    this.setupBottomBar()
    this.setupComments(isNew)

    this._reviewDoneHandler = (e) => {
      if (e.detail?.fieldSlug === this.fieldSlug) {
        this.editor.setEditable(false)
        this.readonly = true
        this.container.querySelector('.te-toolbar-wrap')?.classList.add('hidden')
      }
    }
    window.addEventListener('review-marked-done', this._reviewDoneHandler)
  }

  buildShell() {
    const wrapper = document.createElement('div')

    // Build table grid cells HTML (8 rows × 10 cols)
    let gridCells = ''
    for (let r = 1; r <= 8; r++) {
      for (let c = 1; c <= 10; c++) {
        gridCells += `<span data-row="${r}" data-col="${c}"></span>`
      }
    }

    const isNew = this.recordId === 'new'

    wrapper.innerHTML = `
      <div class="te-shell relative border border-gray-200 rounded-xl shadow-sm overflow-hidden">

        <!-- Presence bar -->
        <div class="te-presence-bar flex items-center gap-2 px-4 py-2 bg-white border-b border-gray-200 min-h-[36px]">
          <span class="text-xs text-gray-400 font-medium">Active users</span>
          <div class="presence-avatars flex items-center gap-1 ml-1"></div>
          <div class="ml-auto flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-gray-300 status-indicator"></span>
            <span class="text-xs text-gray-400 status-label">Offline</span>
          </div>
        </div>

        <!-- Toolbar wrap (sticky) -->
        <div class="te-toolbar-wrap">

          <!-- Main toolbar row -->
          <div class="te-toolbar-row">
            <button type="button" data-cmd="undo" title="Undo" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 14 4 9 9 4"/><path d="M20 20v-7a4 4 0 0 0-4-4H4"/></svg>
            </button>
            <button type="button" data-cmd="redo" title="Redo" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 14 20 9 15 4"/><path d="M4 20v-7a4 4 0 0 1 4-4h12"/></svg>
            </button>
            <span class="te-divider"></span>

            <select class="te-font-family te-select" title="Font family" style="min-width:120px">
              <option value="Arial">Arial</option>
              <option value="Georgia">Georgia</option>
              <option value="Times New Roman" selected>Times New Roman</option>
              <option value="Courier New">Courier New</option>
              <option value="Verdana">Verdana</option>
            </select>
            <select class="te-font-size te-select" title="Font size" style="width:58px">
              <option value="8">8</option>
              <option value="9">9</option>
              <option value="10">10</option>
              <option value="11">11</option>
              <option value="12" selected>12</option>
              <option value="14">14</option>
              <option value="16">16</option>
              <option value="18">18</option>
              <option value="24">24</option>
              <option value="30">30</option>
              <option value="36">36</option>
              <option value="48">48</option>
            </select>
            <span class="te-divider"></span>

            <button type="button" data-cmd="bold" title="Bold" class="te-btn" style="font-weight:700;font-size:13px;min-width:28px">B</button>
            <button type="button" data-cmd="italic" title="Italic" class="te-btn" style="font-style:italic;font-size:13px;min-width:28px">I</button>
            <button type="button" data-cmd="underline" title="Underline" class="te-btn" style="text-decoration:underline;font-size:13px;min-width:28px">U</button>
            <button type="button" data-cmd="strike" title="Strikethrough" class="te-btn" style="text-decoration:line-through;font-size:13px;min-width:28px">S</button>
            <span class="te-divider"></span>

            <span class="te-divider"></span>

            <button type="button" data-cmd="bulletList" title="Bullet list" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="9" y1="6" x2="20" y2="6"/><line x1="9" y1="12" x2="20" y2="12"/><line x1="9" y1="18" x2="20" y2="18"/><circle cx="4" cy="6" r="1" fill="currentColor"/><circle cx="4" cy="12" r="1" fill="currentColor"/><circle cx="4" cy="18" r="1" fill="currentColor"/></svg>
            </button>
            <button type="button" data-cmd="orderedList" title="Numbered list" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="10" y1="6" x2="21" y2="6"/><line x1="10" y1="12" x2="21" y2="12"/><line x1="10" y1="18" x2="21" y2="18"/><path d="M4 6h1v4"/><path d="M4 10h2"/><path d="M6 18H4c0-1 2-2 2-3s-1-1.5-2-1"/></svg>
            </button>
            <button type="button" data-cmd="outdent" title="Outdent" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/><polyline points="7 8 3 12 7 16"/></svg>
            </button>
            <button type="button" data-cmd="indent" title="Indent" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="21" y1="6" x2="11" y2="6"/><line x1="21" y1="12" x2="11" y2="12"/><line x1="21" y1="18" x2="11" y2="18"/><polyline points="3 8 7 12 3 16"/></svg>
            </button>
            <button type="button" data-cmd="blockquote" title="Blockquote" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21c3 0 7-1 7-8V5c0-1.25-.756-2.017-2-2H4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2 1 0 1 0 1 1v1c0 1-1 2-2 2s-1 .008-1 1.031V20c0 1 0 1 1 1z"/><path d="M15 21c3 0 7-1 7-8V5c0-1.25-.757-2.017-2-2h-4c-1.25 0-2 .75-2 1.972V11c0 1.25.75 2 2 2h.75c0 2.25.25 4-2.75 4v3c0 1 0 1 1 1z"/></svg>
            </button>
            <span class="te-divider"></span>

            <button type="button" data-cmd="image" title="${isNew ? 'Save the record first to insert images' : 'Insert image'}" class="te-btn" ${isNew ? 'disabled' : ''}>
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
            </button>
            <button type="button" data-cmd="table" title="Insert table" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="1"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="3" y1="15" x2="21" y2="15"/><line x1="9" y1="3" x2="9" y2="21"/><line x1="15" y1="3" x2="15" y2="21"/></svg>
            </button>

            <span class="te-divider"></span>

            <button type="button" data-cmd="alignLeft" title="Align left" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="15" y2="12"/><line x1="3" y1="18" x2="18" y2="18"/></svg>
            </button>
            <button type="button" data-cmd="alignCenter" title="Center" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="6" y1="12" x2="18" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg>
            </button>
            <button type="button" data-cmd="alignRight" title="Align right" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="9" y1="12" x2="21" y2="12"/><line x1="6" y1="18" x2="21" y2="18"/></svg>
            </button>
            <button type="button" data-cmd="alignJustify" title="Justify" class="te-btn">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
            </button>

            <span style="flex:1"></span>

            <button type="button" data-cmd="sourceToggle" title="Toggle HTML source view" class="te-btn" style="font-size:11px;gap:4px;padding:5px 10px;display:inline-flex;align-items:center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
              Source
            </button>
            <button type="button" data-cmd="pageSetup" title="Page margins" class="te-btn" style="font-size:11px;gap:4px;padding:5px 10px;display:inline-flex;align-items:center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="2" x2="8" y2="22"/><line x1="16" y1="2" x2="16" y2="22"/><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
              Margins
            </button>
            <button type="button" data-cmd="fullscreen" title="Toggle fullscreen" class="te-btn te-fullscreen-btn">
              <svg class="te-icon-expand w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
              <svg class="te-icon-compress w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="display:none"><polyline points="4 14 10 14 10 20"/><polyline points="20 10 14 10 14 4"/><line x1="10" y1="14" x2="3" y2="21"/><line x1="21" y1="3" x2="14" y2="10"/></svg>
            </button>
          </div>

          <!-- Table context bar (hidden until cursor is inside a table) -->
          <div class="te-table-context-bar hidden">
            <button type="button" data-cmd="addRowAfter"  class="te-ctx-btn">+ Row ↓</button>
            <button type="button" data-cmd="addRowBefore" class="te-ctx-btn">+ Row ↑</button>
            <button type="button" data-cmd="deleteRow"    class="te-ctx-btn te-ctx-btn-danger">− Row</button>
            <span class="te-divider" style="height:16px"></span>
            <button type="button" data-cmd="addColAfter"  class="te-ctx-btn">+ Col →</button>
            <button type="button" data-cmd="addColBefore" class="te-ctx-btn">+ Col ←</button>
            <button type="button" data-cmd="deleteCol"    class="te-ctx-btn te-ctx-btn-danger">− Col</button>
            <span class="te-divider" style="height:16px"></span>
            <button type="button" data-cmd="mergeCells"   class="te-ctx-btn">Merge</button>
            <button type="button" data-cmd="splitCell"    class="te-ctx-btn">Split</button>
          </div>
        </div>

        <!-- Source HTML textarea (hidden by default) -->
        <textarea class="te-source-textarea" style="display:none;width:100%;min-height:400px;font-family:monospace;font-size:12px;padding:16px;border:none;outline:none;background:#1e1e1e;color:#d4d4d4;resize:vertical;tab-size:2"></textarea>

        <!-- Editor body: page area + right side panels -->
        <div class="te-editor-body">

          <!-- Google Docs page area -->
          <div class="te-outer-wrap">
            <div class="te-page-row">
              <div class="te-line-numbers-gutter"></div>
              <div class="te-page">
                <div class="text-editor-content"></div>
              </div>
            </div>
          </div>

          <!-- Side panels (right column — hidden until a panel is opened) -->
          <div class="te-side-panels hidden">

            <!-- History side panel -->
            <div class="history-panel te-side-panel hidden">
              <div class="te-side-panel-header">
                <span class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                  Edit History
                </span>
                <button type="button" class="history-close-btn te-panel-close-btn" title="Close panel">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
              <div class="te-side-panel-content">
                <div class="history-list space-y-1 text-xs text-gray-600"></div>
              </div>
            </div>

            <!-- Comments side panel -->
            <div class="comments-panel te-side-panel hidden">
              <div class="te-side-panel-header">
                <span class="flex items-center gap-1.5">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                  Comments
                  <span class="comments-count hidden bg-indigo-500 text-white rounded-full px-1.5 text-[10px] font-bold leading-none py-0.5"></span>
                </span>
                <button type="button" class="comments-close-btn te-panel-close-btn" title="Close panel">
                  <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                </button>
              </div>
              <div class="te-side-panel-content">
                <div class="comments-list space-y-2 text-xs text-gray-600"></div>
              </div>
            </div>

          </div>
        </div>

        <!-- Bottom bar -->
        <div class="te-bottom-bar flex flex-wrap items-center gap-3 px-4 py-2.5 border-t border-gray-100 bg-gray-50 text-xs text-gray-400">
          <span class="word-count">0 words</span>
          <span class="flex-1"></span>
          <button type="button" class="history-toggle-btn te-action-btn hidden" title="Toggle edit history">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            History
          </button>
          <button type="button" class="comments-toggle-btn te-action-btn" title="Toggle comments">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
            Comments
          </button>
          <button type="button" class="export-btn te-action-btn" title="Export as Word document">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            Export .docx
          </button>
        </div>

        <!-- Hidden image file input (inside shell so it's in the editor's DOM) -->
        <input type="file" accept="image/*" class="te-image-input" style="display:none">
      </div>

      <!-- Floating "Add Comment" tooltip (appended to body) -->
      <div class="te-comment-tooltip" style="display:none;position:absolute;z-index:9999;">
        <button type="button" class="te-add-comment-btn" title="Add comment">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
          Comment
        </button>
      </div>

      <!-- Comment input popover (appended to body) -->
      <div class="te-comment-popover" style="display:none;position:absolute;z-index:10000;">
        <div class="te-comment-popover-inner">
          <textarea class="te-comment-input" rows="3" placeholder="Add a comment…"></textarea>
          <div class="flex gap-2 mt-2">
            <button type="button" class="te-comment-submit">Submit</button>
            <button type="button" class="te-comment-cancel">Cancel</button>
          </div>
        </div>
      </div>

      <!-- Table grid picker (appended to body) -->
      <div class="te-table-grid-picker" style="display:none;position:fixed;z-index:10001;">
        <div class="te-grid-cells">${gridCells}</div>
        <p class="te-grid-label">Insert table</p>
      </div>

      <!-- Page setup / margin controls (appended to body) -->
      <div class="te-page-setup-dropdown" style="display:none;position:fixed;z-index:10001;">
        <p style="font-size:11px;font-weight:600;color:#374151;margin:0 0 8px">Page Size</p>
        <select class="te-page-size-select" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:4px 6px;font-size:12px;color:#374151;background:white;margin-bottom:12px;box-sizing:border-box">
          <option value="a4">A4 (794 × 1123 px)</option>
          <option value="letter">Letter (816 × 1056 px)</option>
          <option value="legal">Legal (816 × 1344 px)</option>
          <option value="auto">Auto (fill container)</option>
        </select>
        <p style="font-size:11px;font-weight:600;color:#374151;margin:0 0 10px">Page Margins (px)</p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:10px">
          <label style="font-size:11px;color:#6b7280;display:block">Top<br>
            <input type="number" class="te-margin-input" data-side="top" min="0" max="300" value="20" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:4px 6px;font-size:12px;margin-top:3px;box-sizing:border-box">
          </label>
          <label style="font-size:11px;color:#6b7280;display:block">Right<br>
            <input type="number" class="te-margin-input" data-side="right" min="0" max="300" value="20" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:4px 6px;font-size:12px;margin-top:3px;box-sizing:border-box">
          </label>
          <label style="font-size:11px;color:#6b7280;display:block">Bottom<br>
            <input type="number" class="te-margin-input" data-side="bottom" min="0" max="300" value="20" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:4px 6px;font-size:12px;margin-top:3px;box-sizing:border-box">
          </label>
          <label style="font-size:11px;color:#6b7280;display:block">Left<br>
            <input type="number" class="te-margin-input" data-side="left" min="0" max="300" value="20" style="width:100%;border:1px solid #d1d5db;border-radius:4px;padding:4px 6px;font-size:12px;margin-top:3px;box-sizing:border-box">
          </label>
        </div>
        <button type="button" class="te-margin-reset" style="font-size:11px;color:#6366f1;background:none;border:none;cursor:pointer;padding:0">Reset to Default</button>
      </div>
    `

    // Grab body-appended elements before appending firstElementChild
    this.commentTooltip    = wrapper.querySelector('.te-comment-tooltip')
    this.commentPopover    = wrapper.querySelector('.te-comment-popover')
    this.tableGridPicker   = wrapper.querySelector('.te-table-grid-picker')
    this.pageSetupDropdown = wrapper.querySelector('.te-page-setup-dropdown')

    if (this.commentTooltip)    document.body.appendChild(this.commentTooltip)
    if (this.commentPopover)    document.body.appendChild(this.commentPopover)
    if (this.tableGridPicker)   document.body.appendChild(this.tableGridPicker)
    if (this.pageSetupDropdown) document.body.appendChild(this.pageSetupDropdown)

    this.container.appendChild(wrapper.firstElementChild) // .te-shell

    // Query element references
    this.editorEl         = this.container.querySelector('.text-editor-content')
    this.presenceEl       = this.container.querySelector('.presence-avatars')
    this.statusDot        = this.container.querySelector('.status-indicator')
    this.statusLabel      = this.container.querySelector('.status-label')
    this.wordCountEl      = this.container.querySelector('.word-count')
    this.historyPanel     = this.container.querySelector('.history-panel')
    this.historyList      = this.container.querySelector('.history-list')
    this.historyToggle    = this.container.querySelector('.history-toggle-btn')
    this.exportBtn        = this.container.querySelector('.export-btn')
    this.commentsPanel    = this.container.querySelector('.comments-panel')
    this.commentsList     = this.container.querySelector('.comments-list')
    this.commentsToggle   = this.container.querySelector('.comments-toggle-btn')
    this.commentsCount    = this.container.querySelector('.comments-count')
    this.tableContextBar  = this.container.querySelector('.te-table-context-bar')
    this.lineNumbersGutter = this.container.querySelector('.te-line-numbers-gutter')
    this.pageSizeSelect   = this.pageSetupDropdown?.querySelector('.te-page-size-select')
    this.fontFamilySelect = this.container.querySelector('.te-font-family')
    this.fontSizeSelect   = this.container.querySelector('.te-font-size')
    this.imageInput       = this.container.querySelector('.te-image-input')

    if (this.logHistory) {
      this.historyToggle?.classList.remove('hidden')
    }

    // Inject base CSS once per page
    if (!document.getElementById('text-editor-styles')) {
      const style = document.createElement('style')
      style.id = 'text-editor-styles'
      style.textContent = `
        /* Toolbar buttons */
        .te-btn {
          display:inline-flex; align-items:center; justify-content:center;
          padding:5px 7px; border-radius:6px; color:#6b7280;
          transition:all 0.1s; border:none; background:transparent; cursor:pointer;
          min-width:28px; min-height:28px;
        }
        .te-btn:hover { background:#f3f4f6; color:#111827; }
        .te-btn.is-active { background:#ede9fe; color:#6d28d9; }
        .te-btn:disabled { opacity:0.4; cursor:not-allowed; pointer-events:none; }
        .te-action-btn {
          display:inline-flex; align-items:center; gap:4px;
          padding:4px 10px; border-radius:6px; border:1px solid #e5e7eb;
          background:white; color:#6b7280; cursor:pointer; font-size:11px;
          transition:all 0.15s;
        }
        .te-action-btn:hover { background:#f9fafb; border-color:#d1d5db; color:#374151; }
        .te-review-done-btn {
          display:inline-flex; align-items:center; gap:5px;
          padding:6px 14px; border-radius:8px;
          background:#059669; color:white; font-size:12px; font-weight:500;
          border:none; cursor:pointer; transition:background 0.15s;
        }
        .te-review-done-btn:hover { background:#047857; }
        .te-review-done-btn:disabled { background:#9ca3af; cursor:not-allowed; }
        /* Table context bar buttons */
        .te-ctx-btn {
          display:inline-flex; align-items:center;
          padding:2px 8px; border-radius:4px; font-size:11px; font-weight:500;
          border:none; background:transparent; color:#374151; cursor:pointer; white-space:nowrap;
        }
        .te-ctx-btn:hover { background:#e5e7eb; }
        .te-ctx-btn-danger:hover { background:#fee2e2; color:#dc2626; }

        /* Editor body: page + right-side panels */
        .te-editor-body {
          display: flex;
          flex-direction: row;
          align-items: stretch;
          height: 600px;
          overflow: hidden;
        }
        .te-editor-body > .te-outer-wrap {
          flex: 1;
          min-width: 0;
          overflow-y: auto;
        }
        .te-side-panels {
          width: 280px;
          flex-shrink: 0;
          display: flex;
          flex-direction: column;
          border-left: 1px solid #e5e7eb;
          background: #fff;
          overflow: hidden;
          height: 100%;
        }
        .te-side-panels.hidden { display: none; }
        .te-side-panel {
          display: flex;
          flex-direction: column;
          overflow: hidden;
          min-height: 0;
          border-bottom: 1px solid #f3f4f6;
        }
        .te-side-panel.hidden { display: none; }
        .te-side-panel:not(.hidden) { flex: 1; }
        .te-side-panel-header {
          display: flex;
          align-items: center;
          justify-content: space-between;
          padding: 8px 12px;
          background: #f9fafb;
          border-bottom: 1px solid #e5e7eb;
          font-size: 11px;
          font-weight: 600;
          color: #374151;
          flex-shrink: 0;
          gap: 8px;
        }
        .te-side-panel-content {
          flex: 1;
          overflow-y: auto;
          padding: 10px 12px;
        }
        .te-panel-close-btn {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          width: 22px;
          height: 22px;
          border-radius: 4px;
          border: none;
          background: transparent;
          color: #9ca3af;
          cursor: pointer;
          flex-shrink: 0;
        }
        .te-panel-close-btn:hover { background: #f3f4f6; color: #374151; }

        /* Google Docs page layout */
        .te-outer-wrap {
          background: #e8eaed;
          padding: 24px 32px 40px;
          min-height: 420px;
          overflow-x: auto;
        }
        .te-page-row {
          display: flex;
          align-items: flex-start;
          padding-bottom: 40px;
        }
        .te-line-numbers-gutter {
          width: 36px;
          flex-shrink: 0;
          background: #e8eaed;
          position: relative;
          min-height: 600px;
          align-self: stretch;
          border-right: 1px solid #c8c8c8;
          overflow: hidden;
        }
        .te-line-numbers-gutter span {
          position: absolute;
          right: 4px;
          font-size: 10px;
          color: #888;
          font-family: Arial, sans-serif;
          user-select: none;
          line-height: 1;
          transform: translateY(-50%);
          white-space: nowrap;
        }
        .te-page {
          flex-shrink: 0;
          min-height: 600px;
          background: white;
          box-sizing: border-box;
          box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 4px 12px rgba(0,0,0,.08);
          padding: 20px;
          font-family: 'Times New Roman', Times, serif;
          font-size: 12pt;
          line-height: 1.5;
        }
        .te-page.te-page-auto {
          flex: 1;
          min-width: 0;
          width: auto !important;
        }

        /* Toolbar */
        .te-toolbar-wrap {
          position: sticky; top: 0; z-index: 20;
          background: #fff; border-bottom: 1px solid #e0e0e0;
          padding: 4px 8px;
        }
        .te-toolbar-row {
          display: flex; align-items: center; flex-wrap: wrap; gap: 2px;
        }
        .te-table-context-bar {
          display: flex; align-items: center; flex-wrap: wrap; gap: 2px;
          padding: 2px 4px; border-top: 1px solid #e0e0e0; background: #f8f9fa;
        }
        .te-divider {
          width: 1px; height: 20px; background: #e0e0e0;
          margin: 0 4px; flex-shrink: 0; display: inline-block;
        }
        .te-select {
          border: 1px solid #e0e0e0; border-radius: 4px;
          background: white; font-size: 12px; padding: 3px 6px;
          color: #374151; cursor: pointer; height: 28px;
        }
        .te-select:hover { border-color: #9ca3af; background: #f9fafb; }

        /* Table grid picker */
        .te-table-grid-picker {
          background: white; border: 1px solid #e5e7eb;
          border-radius: 8px; padding: 8px;
          box-shadow: 0 4px 16px rgba(0,0,0,.12);
        }
        .te-grid-cells {
          display: grid; grid-template-columns: repeat(10, 18px); gap: 2px;
        }
        .te-grid-cells span {
          width: 18px; height: 18px; border: 1px solid #d1d5db;
          border-radius: 2px; cursor: pointer; display: block;
        }
        .te-grid-cells span.te-cell-active { background: #bfdbfe; border-color: #3b82f6; }
        .te-grid-label {
          text-align: center; font-size: 11px; color: #6b7280;
          margin-top: 6px; margin-bottom: 0;
        }
        /* Page setup dropdown */
        .te-page-setup-dropdown {
          background: white; border: 1px solid #e5e7eb;
          border-radius: 8px; padding: 12px; width: 240px;
          box-shadow: 0 4px 16px rgba(0,0,0,.12);
        }

        /* ProseMirror inside page */
        .te-page .ProseMirror { outline: none; min-height: 400px; }
        /* Lists */
        .te-page .ProseMirror ul { list-style-type: disc; padding-left: 1.5em; margin: 0.25em 0; }
        .te-page .ProseMirror ol { list-style-type: decimal; padding-left: 1.5em; margin: 0.25em 0; }
        .te-page .ProseMirror ul ul { list-style-type: circle; }
        .te-page .ProseMirror ul ul ul { list-style-type: square; }
        .te-page .ProseMirror li { margin: 0.1em 0; }
        /* Paragraph indent */
        .te-page .ProseMirror [data-indent="1"] { margin-left: 2em; }
        .te-page .ProseMirror [data-indent="2"] { margin-left: 4em; }
        .te-page .ProseMirror [data-indent="3"] { margin-left: 6em; }
        .te-page .ProseMirror [data-indent="4"] { margin-left: 8em; }
        .te-page .ProseMirror [data-indent="5"] { margin-left: 10em; }
        .te-page .ProseMirror p.is-editor-empty:first-child::before {
          content: attr(data-placeholder); color: #9ca3af; pointer-events: none;
          height: 0; float: left;
        }
        /* Tables */
        .te-page table { border-collapse: collapse; width: 100%; margin: 8px 0; }
        .te-page td, .te-page th {
          border: 1px solid #d1d5db; padding: 6px 10px; min-width: 60px; vertical-align: top;
        }
        .te-page th { background: #f3f4f6; font-weight: 600; }
        .selectedCell { background: #dbeafe !important; }
        /* Images */
        .te-page img { max-width: 100%; height: auto; display: block; margin: 8px 0; cursor: default; }

        /* Inline comment highlight */
        .te-comment-highlight {
          background: #fef08a; border-bottom: 2px solid #eab308;
          cursor: pointer; border-radius: 2px;
        }
        .te-comment-highlight:hover { background: #fde047; }
        /* Floating add-comment button */
        .te-comment-tooltip { pointer-events: auto; }
        .te-add-comment-btn {
          display:inline-flex; align-items:center; gap:5px;
          padding:5px 10px; border-radius:6px;
          background:#1e1b4b; color:white; font-size:11px; font-weight:500;
          border:none; cursor:pointer; white-space:nowrap; box-shadow:0 2px 8px rgba(0,0,0,0.2);
        }
        .te-add-comment-btn:hover { background:#312e81; }
        /* Comment input popover */
        .te-comment-popover-inner {
          background:white; border:1px solid #e5e7eb; border-radius:10px;
          padding:12px; box-shadow:0 8px 24px rgba(0,0,0,0.12); width:260px;
        }
        .te-comment-input {
          width:100%; border:1px solid #d1d5db; border-radius:6px;
          padding:6px 8px; font-size:12px; resize:none; outline:none; font-family:inherit;
        }
        .te-comment-input:focus { border-color:#6366f1; }
        .te-comment-submit {
          flex:1; padding:5px 12px; border-radius:6px;
          background:#4f46e5; color:white; font-size:11px; font-weight:500;
          border:none; cursor:pointer;
        }
        .te-comment-submit:hover { background:#4338ca; }
        .te-comment-cancel {
          padding:5px 10px; border-radius:6px; font-size:11px;
          background:transparent; color:#6b7280; border:1px solid #e5e7eb; cursor:pointer;
        }
        .te-comment-cancel:hover { background:#f9fafb; }
        @keyframes te-flash { 0%,100%{background:#fef9c3} 50%{background:#fde047} }
        .te-comment-flash { animation: te-flash 0.6s ease 2; }
        /* Collaboration cursors */
        .collaboration-cursor__caret {
          border-left: 2px solid; border-right: none;
          margin-left: -1px; margin-right: -1px;
          pointer-events: none; position: relative; word-break: normal;
        }
        .collaboration-cursor__label {
          border-radius: 3px 3px 3px 0; font-size: 11px; font-weight:600;
          left: -1px; line-height: normal; padding: 1px 5px;
          position: absolute; top: -1.4em; user-select: none;
          white-space: nowrap; color: white;
        }
        /* Fullscreen */
        .te-shell.te-fullscreen {
          position: fixed !important;
          inset: 0 !important;
          z-index: 9990 !important;
          border-radius: 0 !important;
          display: flex !important;
          flex-direction: column !important;
        }
        .te-shell.te-fullscreen .te-editor-body {
          flex: 1;
          height: auto !important;
          min-height: 0;
          overflow: hidden;
        }
        .te-shell.te-fullscreen .te-outer-wrap {
          flex: 1;
          overflow-y: auto;
          min-height: 0;
        }
        .te-shell.te-fullscreen .te-side-panels {
          height: 100%;
        }
        .te-shell.te-fullscreen .te-toolbar-wrap {
          flex-shrink: 0;
        }
      `
      document.head.appendChild(style)
    }
  }

  setupToolbar() {
    const toolbarWrap = this.container.querySelector('.te-toolbar-wrap')
    if (!toolbarWrap || this.readonly) {
      toolbarWrap?.classList.add('hidden')
      return
    }

    // Wire all [data-cmd] buttons
    toolbarWrap.querySelectorAll('[data-cmd]').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault()
        const cmd = btn.dataset.cmd
        const c = this.editor.chain().focus()
        switch (cmd) {
          case 'bold':         c.toggleBold().run(); break
          case 'italic':       c.toggleItalic().run(); break
          case 'underline':    c.toggleUnderline().run(); break
          case 'strike':       c.toggleStrike().run(); break
          case 'h1':           c.toggleHeading({ level: 1 }).run(); break
          case 'h2':           c.toggleHeading({ level: 2 }).run(); break
          case 'h3':           c.toggleHeading({ level: 3 }).run(); break
          case 'bulletList':   c.toggleBulletList().run(); break
          case 'orderedList':  c.toggleOrderedList().run(); break
          case 'indent':       c.indent().run(); break
          case 'outdent':      c.outdent().run(); break
          case 'blockquote':   c.toggleBlockquote().run(); break
          case 'alignLeft':    c.setTextAlign('left').run(); break
          case 'alignCenter':  c.setTextAlign('center').run(); break
          case 'alignRight':   c.setTextAlign('right').run(); break
          case 'alignJustify': c.setTextAlign('justify').run(); break
          case 'undo':         c.undo().run(); break
          case 'redo':         c.redo().run(); break
          case 'image':        this.imageInput?.click(); break
          case 'table':        this.toggleTablePicker(btn); break
          case 'pageSetup':    this.togglePageSetup(btn); break
          case 'sourceToggle': this.toggleSourceView(); break
          case 'fullscreen':   this.toggleFullscreen(); break
          case 'addRowAfter':  c.addRowAfter().run(); break
          case 'addRowBefore': c.addRowBefore().run(); break
          case 'deleteRow':    c.deleteRow().run(); break
          case 'addColAfter':  c.addColumnAfter().run(); break
          case 'addColBefore': c.addColumnBefore().run(); break
          case 'deleteCol':    c.deleteColumn().run(); break
          case 'mergeCells':   c.mergeCells().run(); break
          case 'splitCell':    c.splitCell().run(); break
        }
        if (!['table', 'pageSetup', 'image', 'sourceToggle'].includes(cmd)) {
          this.updateToolbarState()
        }
      })
    })

    // Font family select
    this.fontFamilySelect?.addEventListener('change', e => {
      const v = e.target.value
      const c = this.editor.chain().focus()
      v ? c.setFontFamily(v).run() : c.unsetFontFamily().run()
    })

    // Font size select
    this.fontSizeSelect?.addEventListener('change', e => {
      const v = e.target.value
      const c = this.editor.chain().focus()
      v ? c.setFontSize(v).run() : c.unsetFontSize().run()
    })

    // Image file input
    this.imageInput?.addEventListener('change', e => {
      const file = e.target.files[0]
      if (file) this.uploadImage(file)
      e.target.value = ''
    })

    // Table grid picker — hover to highlight, click to insert
    if (this.tableGridPicker) {
      const cells = this.tableGridPicker.querySelectorAll('.te-grid-cells span')
      const label = this.tableGridPicker.querySelector('.te-grid-label')

      cells.forEach(cell => {
        cell.addEventListener('mouseover', () => {
          const r = +cell.dataset.row
          const c = +cell.dataset.col
          cells.forEach(s => {
            s.classList.toggle('te-cell-active', +s.dataset.row <= r && +s.dataset.col <= c)
          })
          if (label) label.textContent = `${r} × ${c} table`
        })

        cell.addEventListener('click', () => {
          const r = +cell.dataset.row
          const c = +cell.dataset.col
          this.editor.chain().focus().insertTable({ rows: r, cols: c, withHeaderRow: true }).run()
          this.tableGridPicker.style.display = 'none'
          cells.forEach(s => s.classList.remove('te-cell-active'))
          if (label) label.textContent = 'Insert table'
        })
      })

      this.tableGridPicker.addEventListener('mouseleave', () => {
        cells.forEach(s => s.classList.remove('te-cell-active'))
        if (label) label.textContent = 'Insert table'
      })
    }

    // Page setup — size select
    if (this.pageSizeSelect) {
      this.pageSizeSelect.value = this.pageSize
      this.pageSizeSelect.addEventListener('change', () => {
        this.pageSize = this.pageSizeSelect.value
        this.applyPageSize()
      })
    }

    // Page setup margin inputs
    if (this.pageSetupDropdown) {
      this.pageSetupDropdown.querySelectorAll('.te-margin-input').forEach(input => {
        input.addEventListener('input', () => {
          const side = input.dataset.side
          const val  = Math.max(0, Math.min(300, parseInt(input.value, 10) || 0))
          this.margins[side] = val
          this.applyMargins()
        })
      })

      this.pageSetupDropdown.querySelector('.te-margin-reset')?.addEventListener('click', () => {
        this.margins = { top: 20, right: 20, bottom: 20, left: 20 }
        this.applyMargins()
        this.pageSetupDropdown.querySelectorAll('.te-margin-input').forEach(inp => {
          inp.value = 20
        })
      })
    }

    // Outside-click: close table picker and page setup
    document.addEventListener('mousedown', (e) => {
      const tableBtn = this.container.querySelector('[data-cmd="table"]')
      const setupBtn = this.container.querySelector('[data-cmd="pageSetup"]')

      if (this.tableGridPicker?.style.display !== 'none') {
        if (!this.tableGridPicker.contains(e.target) && !tableBtn?.contains(e.target)) {
          this.tableGridPicker.style.display = 'none'
        }
      }
      if (this.pageSetupDropdown?.style.display !== 'none') {
        if (!this.pageSetupDropdown.contains(e.target) && !setupBtn?.contains(e.target)) {
          this.pageSetupDropdown.style.display = 'none'
        }
      }
    })

    this.editor.on('selectionUpdate', () => this.updateToolbarState())
    this.editor.on('transaction',     () => {
      this.updateToolbarState()
      this.updateWordCount()
    })
  }

  updateToolbarState() {
    const toolbarWrap = this.container.querySelector('.te-toolbar-wrap')
    if (!toolbarWrap) return
    const e = this.editor
    const states = {
      bold:        e.isActive('bold'),
      italic:      e.isActive('italic'),
      underline:   e.isActive('underline'),
      strike:      e.isActive('strike'),
      h1:          e.isActive('heading', { level: 1 }),
      h2:          e.isActive('heading', { level: 2 }),
      h3:          e.isActive('heading', { level: 3 }),
      bulletList:  e.isActive('bulletList'),
      orderedList: e.isActive('orderedList'),
      blockquote:   e.isActive('blockquote'),
      table:        e.isActive('table'),
      alignLeft:    e.isActive({ textAlign: 'left' }),
      alignCenter:  e.isActive({ textAlign: 'center' }),
      alignRight:   e.isActive({ textAlign: 'right' }),
      alignJustify: e.isActive({ textAlign: 'justify' }),
    }
    toolbarWrap.querySelectorAll('[data-cmd]').forEach(btn => {
      btn.classList.toggle('is-active', !!states[btn.dataset.cmd])
    })

    // Sync font selects
    if (this.fontFamilySelect) {
      this.fontFamilySelect.value = e.getAttributes('textStyle').fontFamily || ''
    }
    if (this.fontSizeSelect) {
      this.fontSizeSelect.value = e.getAttributes('textStyle').fontSize || ''
    }

    // Show/hide table context bar
    this.tableContextBar?.classList.toggle('hidden', !e.isActive('table'))
  }

  setupBottomBar() {
    const sidePanels = this.container.querySelector('.te-side-panels')

    const syncSidePanels = () => {
      const anyOpen = !this.historyPanel.classList.contains('hidden') ||
                      !this.commentsPanel.classList.contains('hidden')
      sidePanels?.classList.toggle('hidden', !anyOpen)
    }

    this.historyToggle?.addEventListener('click', () => {
      const opening = this.historyPanel.classList.contains('hidden')
      this.historyPanel.classList.toggle('hidden', !opening)
      syncSidePanels()
      if (opening && this.recordId !== 'new') this.loadHistory()
    })

    this.container.querySelector('.history-close-btn')?.addEventListener('click', () => {
      this.historyPanel.classList.add('hidden')
      syncSidePanels()
    })

    this.exportBtn?.addEventListener('click', () => this.exportDocx())

    this._syncSidePanels = syncSidePanels
  }

  updateStatus(status) {
    const colors = {
      connected:    '#f59e0b',
      synced:       '#10b981',
      disconnected: '#6b7280',
    }
    const labels = {
      connected:    'Connecting…',
      synced:       'Live',
      disconnected: 'Offline',
    }
    if (this.statusDot)   this.statusDot.style.backgroundColor = colors[status] || '#6b7280'
    if (this.statusLabel) this.statusLabel.textContent = labels[status] || status

    if (this.provider && status === 'synced') {
      this.provider.on('awarenessChange', () => this.updatePresence())
    }
  }

  updatePresence() {
    if (!this.provider || !this.presenceEl) return
    const states = this.provider.awareness?.getStates()
    if (!states) return

    const users = []
    states.forEach((state, clientId) => {
      if (clientId !== this.ydoc.clientID && state.user) {
        users.push(state.user)
      }
    })

    this.presenceEl.innerHTML = users.slice(0, 5).map(u => `
      <span title="${u.name}" style="
        display:inline-flex; align-items:center; justify-content:center;
        width:24px; height:24px; border-radius:50%;
        background:${u.color}; color:white; font-size:10px; font-weight:600;
        border:2px solid white; margin-left:-4px;
      ">${u.name.charAt(0).toUpperCase()}</span>
    `).join('')
  }

  updateWordCount() {
    if (!this.wordCountEl || !this.editor) return
    const text = this.editor.getText()
    const words = text.trim() ? text.trim().split(/\s+/).length : 0
    this.wordCountEl.textContent = `${words} word${words !== 1 ? 's' : ''}`
  }

  syncToLivewire(html) {
    if (!this.hiddenInput) return
    this.hiddenInput.value = html
    // Dispatch input event so Livewire's wire:model deferred binding
    // picks up the latest value before any action request fires.
    this.hiddenInput.dispatchEvent(new Event('input', { bubbles: true }))
  }

  // Called by the Livewire commit hook (and save buttons) to ensure the
  // latest editor HTML is queued into the Livewire component before a request.
  pushToLivewire() {
    if (!this.editor) return
    const html = this.editor.getHTML()
    if (this.hiddenInput) this.hiddenInput.value = html
    const wireEl = (this.hiddenInput || this.container).closest('[wire\\:id]')
    if (!wireEl) return
    try {
      const component = Livewire.find(wireEl.getAttribute('wire:id'))
      if (component) component.set(`data.${this.fieldSlug}`, html)
    } catch (e) { /* ignore */ }
  }

  toggleTablePicker(btn) {
    const isHidden = this.tableGridPicker.style.display === 'none' || !this.tableGridPicker.style.display
    if (!isHidden) { this.tableGridPicker.style.display = 'none'; return }
    const rect = btn.getBoundingClientRect()
    this.tableGridPicker.style.top  = (rect.bottom + 4) + 'px'
    this.tableGridPicker.style.left = rect.left + 'px'
    this.tableGridPicker.style.display = 'block'
  }

  togglePageSetup(btn) {
    const isHidden = this.pageSetupDropdown.style.display === 'none' || !this.pageSetupDropdown.style.display
    if (!isHidden) { this.pageSetupDropdown.style.display = 'none'; return }
    const rect = btn.getBoundingClientRect()
    this.pageSetupDropdown.style.top  = (rect.bottom + 4) + 'px'
    this.pageSetupDropdown.style.left = Math.max(4, rect.right - 220) + 'px'
    this.pageSetupDropdown.style.display = 'block'
  }

  toggleFullscreen() {
    const shell = this.container.querySelector('.te-shell')
    if (!shell) return
    const entering = !shell.classList.contains('te-fullscreen')
    shell.classList.toggle('te-fullscreen', entering)
    document.body.style.overflow = entering ? 'hidden' : ''

    // Swap the icon
    const btn = this.container.querySelector('[data-cmd="fullscreen"]')
    btn?.querySelector('.te-icon-expand')?.style.setProperty('display', entering ? 'none' : '')
    btn?.querySelector('.te-icon-compress')?.style.setProperty('display', entering ? '' : 'none')

    // Escape key exits fullscreen
    if (entering) {
      this._fsEscHandler = (e) => { if (e.key === 'Escape') this.toggleFullscreen() }
      document.addEventListener('keydown', this._fsEscHandler)
    } else if (this._fsEscHandler) {
      document.removeEventListener('keydown', this._fsEscHandler)
      this._fsEscHandler = null
    }

    requestAnimationFrame(() => this.updateLineNumbers())
  }

  toggleSourceView() {
    const sourceEl  = this.container.querySelector('.te-source-textarea')
    const editorBody = this.container.querySelector('.te-editor-body')
    const btn = this.container.querySelector('[data-cmd="sourceToggle"]')
    if (!sourceEl || !editorBody) return

    const isSource = sourceEl.style.display !== 'none'

    if (isSource) {
      // Apply source HTML back to editor
      this.editor.commands.setContent(sourceEl.value, true)
      sourceEl.style.display = 'none'
      editorBody.style.display = ''
      btn?.classList.remove('is-active')
    } else {
      // Show raw HTML in source textarea
      sourceEl.value = this.editor.getHTML()
      sourceEl.style.display = 'block'
      editorBody.style.display = 'none'
      btn?.classList.add('is-active')
    }
  }

  applyMargins() {
    const page = this.container.querySelector('.te-page')
    if (!page) return
    const m = this.margins
    page.style.padding = `${m.top}px ${m.right}px ${m.bottom}px ${m.left}px`
    requestAnimationFrame(() => this.updateLineNumbers())
  }

  applyPageSize() {
    const page      = this.container.querySelector('.te-page')
    const pageRow   = this.container.querySelector('.te-page-row')
    const outerWrap = this.container.querySelector('.te-outer-wrap')
    if (!page || !pageRow || !outerWrap) return

    const sizes = {
      a4:     { width: 794,  height: 1123 },
      letter: { width: 816,  height: 1056 },
      legal:  { width: 816,  height: 1344 },
    }

    // Fixed gutter: line-numbers-gutter (36px)
    const GUTTER = 36

    if (this.pageSize === 'auto') {
      page.classList.add('te-page-auto')
      page.style.width      = ''
      page.style.minHeight  = '600px'
      pageRow.style.width   = ''
      pageRow.style.maxWidth = ''
      pageRow.style.margin  = ''
      outerWrap.style.minHeight = ''
    } else {
      const s = sizes[this.pageSize] || sizes.a4
      const rowWidth = GUTTER + s.width

      page.classList.remove('te-page-auto')
      page.style.width     = s.width + 'px'
      page.style.minHeight = s.height + 'px'

      pageRow.style.width    = rowWidth + 'px'
      pageRow.style.maxWidth = rowWidth + 'px'
      pageRow.style.margin   = '0 auto'

      // outer wrap tall enough to show the full page plus breathing room
      outerWrap.style.minHeight = (s.height + 64) + 'px'
    }

    requestAnimationFrame(() => this.updateLineNumbers())
  }

  updateLineNumbers() {
    const gutter = this.lineNumbersGutter
    const prose  = this.container.querySelector('.ProseMirror')
    if (!gutter || !prose) return

    const gutterRect = gutter.getBoundingClientRect()
    if (!gutterRect.height) return

    const blocks = prose.querySelectorAll(':scope > *')
    gutter.innerHTML = ''

    blocks.forEach((block, i) => {
      const rect = block.getBoundingClientRect()
      const top  = rect.top - gutterRect.top + rect.height / 2
      if (top < 0 || top > gutterRect.height + 20) return
      const span = document.createElement('span')
      span.textContent = i + 1
      span.style.top = top + 'px'
      gutter.appendChild(span)
    })

    // Watch page resize to keep numbers aligned
    if (!this._rulerObserver) {
      const page = this.container.querySelector('.te-page')
      if (page) {
        this._rulerObserver = new ResizeObserver(() => requestAnimationFrame(() => this.updateLineNumbers()))
        this._rulerObserver.observe(page)
      }
    }
  }

  async uploadImage(file) {
    const fd = new FormData()
    fd.append('image', file)
    try {
      const res = await fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/image`, {
        method: 'POST',
        headers: {
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
        },
        body: fd,
      })
      if (!res.ok) { alert('Image upload failed'); return }
      const { url } = await res.json()
      this.editor.chain().focus().setImage({ src: url }).run()
    } catch {
      alert('Image upload failed')
    }
  }

  logChange(editor) {
    clearTimeout(this.debounceTimer)
    this.debounceTimer = setTimeout(() => {
      const current = editor.getText()
      if (current !== this.lastContent && this.lastContent !== '') {
        const action  = current.length > this.lastContent.length ? 'insert' : 'delete'
        const snippet = (action === 'insert'
          ? current.slice(this.lastContent.length - 1)
          : this.lastContent.slice(current.length - 1)).slice(0, 200).trim()

        if (!snippet) { this.lastContent = current; return }

        fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/history`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'Authorization': `Bearer ${this.token}`,
            'Accept': 'application/json',
          },
          body: JSON.stringify({ action, content: snippet }),
        }).catch(() => {})
      }
      this.lastContent = current
    }, 1500)
  }

  async loadHistory() {
    if (!this.historyList || this.recordId === 'new') return
    this.historyList.innerHTML = '<p class="text-gray-400 italic">Loading…</p>'

    try {
      const res = await fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/history`, {
        headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
      })
      const data = await res.json()

      if (!data.length) {
        this.historyList.innerHTML = '<p class="text-gray-400 italic">No history yet.</p>'
        return
      }

      this.historyList.innerHTML = data.map(entry => `
        <div class="flex items-start gap-2 py-1 border-b border-gray-50 last:border-0">
          <span class="flex-shrink-0 w-12 text-right font-mono ${entry.action === 'insert' ? 'text-emerald-600' : 'text-rose-500'}">
            ${entry.action === 'insert' ? '+' : '−'}
          </span>
          <span class="flex-1 truncate text-gray-700" title="${entry.content?.replace(/"/g, '&quot;')}">
            ${entry.content || '—'}
          </span>
          <span class="flex-shrink-0 text-gray-400 text-right" style="min-width:100px">
            ${entry.user_name} · ${new Date(entry.created_at).toLocaleTimeString()}
          </span>
        </div>
      `).join('')
    } catch {
      this.historyList.innerHTML = '<p class="text-rose-400 italic">Failed to load history.</p>'
    }
  }

  setupComments(isNew) {
    this.commentsToggle?.addEventListener('click', () => {
      const opening = this.commentsPanel.classList.contains('hidden')
      this.commentsPanel.classList.toggle('hidden', !opening)
      this._syncSidePanels?.()
      if (opening && !isNew) this.loadComments()
    })

    this.container.querySelector('.comments-close-btn')?.addEventListener('click', () => {
      this.commentsPanel.classList.add('hidden')
      this._syncSidePanels?.()
    })

    if (isNew) return

    this.editor.on('selectionUpdate', ({ editor }) => {
      const { from, to } = editor.state.selection
      if (from === to || this.readonly) {
        this.commentTooltip.style.display = 'none'
        return
      }
      const coords = editor.view.coordsAtPos(from)
      const scrollY = window.scrollY
      this.commentTooltip.style.display = 'block'
      this.commentTooltip.style.top  = (coords.top + scrollY - 36) + 'px'
      this.commentTooltip.style.left = (coords.left) + 'px'
    })

    document.addEventListener('mousedown', (e) => {
      if (!this.commentTooltip.contains(e.target) && !this.commentPopover.contains(e.target)) {
        this.commentTooltip.style.display = 'none'
      }
    })

    this.commentTooltip.querySelector('.te-add-comment-btn').addEventListener('click', (e) => {
      e.preventDefault()
      const { from, to } = this.editor.state.selection
      if (from === to) return
      this.commentTooltip.style.display = 'none'

      const coords = this.editor.view.coordsAtPos(from)
      const scrollY = window.scrollY
      this.commentPopover.style.display = 'block'
      this.commentPopover.style.top  = (coords.top + scrollY - 10) + 'px'
      this.commentPopover.style.left = (coords.left) + 'px'
      this.commentPopover.querySelector('.te-comment-input').value = ''
      this.commentPopover.querySelector('.te-comment-input').focus()

      this._pendingCommentRange = { from, to }
      this._pendingQuotedText = this.editor.state.doc.textBetween(from, to, ' ')
    })

    this.commentPopover.querySelector('.te-comment-submit').addEventListener('click', () => {
      const body = this.commentPopover.querySelector('.te-comment-input').value.trim()
      if (!body || !this._pendingCommentRange) return
      this.submitComment(body)
    })

    this.commentPopover.querySelector('.te-comment-cancel').addEventListener('click', () => {
      this.commentPopover.style.display = 'none'
      this._pendingCommentRange = null
    })

    this.editorEl.addEventListener('click', (e) => {
      const mark = e.target.closest('[data-comment-id]')
      if (!mark) return
      const commentId = mark.getAttribute('data-comment-id')
      const item = this.commentsList?.querySelector(`[data-comment-id="${commentId}"]`)
      if (item) {
        this.commentsPanel.classList.remove('hidden')
        item.scrollIntoView({ behavior: 'smooth', block: 'nearest' })
        item.classList.add('te-comment-flash')
        setTimeout(() => item.classList.remove('te-comment-flash'), 1200)
      }
    })

    if (this.provider) {
      this.provider.on('synced', () => this.loadComments())
    } else {
      this.loadComments()
    }
  }

  async submitComment(body) {
    const { from, to } = this._pendingCommentRange
    const commentId = generateUUID()

    try {
      const res = await fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/comments`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${this.token}`,
          'Accept': 'application/json',
        },
        body: JSON.stringify({
          comment_id:  commentId,
          quoted_text: this._pendingQuotedText,
          body,
        }),
      })
      if (!res.ok) throw new Error('Failed to save comment')

      this.editor.chain()
        .focus()
        .setTextSelection({ from, to })
        .setMark('inlineComment', { commentId })
        .run()

      this.commentPopover.style.display = 'none'
      this._pendingCommentRange = null

      await this.loadComments()
      this.commentsPanel.classList.remove('hidden')
    } catch {
      alert('Could not save comment. Please try again.')
    }
  }

  async loadComments() {
    if (!this.commentsList || this.recordId === 'new') return

    try {
      const res = await fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/comments`, {
        headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
      })
      const comments = await res.json()

      if (comments.length > 0) {
        this.commentsCount?.classList.remove('hidden')
        if (this.commentsCount) this.commentsCount.textContent = comments.length
      } else {
        this.commentsCount?.classList.add('hidden')
      }

      if (!comments.length) {
        this.commentsList.innerHTML = '<p class="text-gray-400 italic">No comments yet. Select text to add one.</p>'
        return
      }

      this.commentsList.innerHTML = comments.map(c => `
        <div class="te-comment-item border border-gray-100 rounded-lg p-2.5 bg-gray-50 hover:bg-yellow-50 transition-colors cursor-pointer"
             data-comment-id="${c.comment_id}">
          <div class="flex items-start justify-between gap-2">
            <div class="flex-1 min-w-0">
              <p class="text-gray-400 italic truncate mb-1" title="${this.escHtml(c.quoted_text)}">
                "${this.escHtml(c.quoted_text)}"
              </p>
              <p class="text-gray-800 font-normal leading-snug">${this.escHtml(c.body)}</p>
            </div>
            <button type="button" class="te-resolve-btn flex-shrink-0 text-gray-300 hover:text-red-400 transition-colors"
                    data-comment-id="${c.comment_id}" title="Resolve comment">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
          </div>
          <p class="mt-1.5 text-gray-400 font-medium">${this.escHtml(c.user_name)} · ${new Date(c.created_at).toLocaleString()}</p>
        </div>
      `).join('')

      this.commentsList.querySelectorAll('.te-resolve-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          e.stopPropagation()
          this.resolveComment(btn.dataset.commentId)
        })
      })

      this.commentsList.querySelectorAll('.te-comment-item').forEach(item => {
        item.addEventListener('click', () => {
          const id = item.dataset.commentId
          const mark = this.editorEl.querySelector(`[data-comment-id="${id}"]`)
          mark?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        })
      })
    } catch {
      this.commentsList.innerHTML = '<p class="text-rose-400 italic">Failed to load comments.</p>'
    }
  }

  async resolveComment(commentId) {
    try {
      await fetch(`/api/text-editor/${this.recordId}/${this.fieldSlug}/comments/${commentId}`, {
        method: 'DELETE',
        headers: { 'Authorization': `Bearer ${this.token}`, 'Accept': 'application/json' },
      })

      const { state } = this.editor
      const markType = state.schema.marks.inlineComment
      if (markType) {
        const tr = state.tr
        state.doc.descendants((node, pos) => {
          if (!node.isText) return
          node.marks.forEach(mark => {
            if (mark.type === markType && mark.attrs.commentId === commentId) {
              tr.removeMark(pos, pos + node.nodeSize, markType)
            }
          })
        })
        if (tr.docChanged) {
          this.editor.view.dispatch(tr)
        }
      }

      await this.loadComments()
    } catch {
      alert('Could not resolve comment.')
    }
  }

  escHtml(str) {
    return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')
  }

  async exportDocx() {
    if (!this.editor) return

    const btn = this.exportBtn
    const origHTML = btn?.innerHTML
    if (btn) { btn.disabled = true; btn.textContent = 'Exporting…' }

    try {
      await this._doExportDocx()
    } catch (e) {
      console.error('[DOCX] export error:', e)
      alert('Export failed: ' + e.message)
    } finally {
      if (btn) { btn.disabled = false; btn.innerHTML = origHTML }
    }
  }

  async _doExportDocx() {
    const json = this.editor.getJSON()
    console.warn('[DOCX] editor JSON (first 3 nodes):', JSON.stringify(json.content?.slice(0, 3), null, 2))

    // imageCache: src → { buf: ArrayBuffer, width: number, height: number } | null
    const imageCache = {}

    const makeImageRun = (src) => {
      const cached = imageCache[src]
      if (!cached?.buf) {
        console.warn('[DOCX] no image data for:', src)
        return new TextRun('[Image]')
      }
      const scale = cached.width > MAX_IMG_PX ? MAX_IMG_PX / cached.width : 1
      const w = Math.max(1, Math.round((cached.width || 400) * scale))
      const h = Math.max(1, Math.round((cached.height || 300) * scale))
      return new ImageRun({ type: 'png', data: cached.buf, transformation: { width: w, height: h } })
    }

    // Load image via canvas — try DOM img first, fall back to blob-URL fetch
    const loadImage = (src) => new Promise(resolve => {
      const draw = (img) => {
        try {
          const w = img.naturalWidth || 0
          const h = img.naturalHeight || 0
          if (!w || !h) {
            console.warn('[DOCX] image has 0 dimensions:', src)
            return resolve(null)
          }
          const canvas = document.createElement('canvas')
          canvas.width = w
          canvas.height = h
          canvas.getContext('2d').drawImage(img, 0, 0)
          canvas.toBlob(blob => {
            if (!blob) { console.warn('[DOCX] toBlob returned null:', src); return resolve(null) }
            blob.arrayBuffer()
              .then(buf => { console.warn('[DOCX] loaded', src, w + 'x' + h, buf.byteLength + 'b'); resolve({ buf, width: w, height: h }) })
              .catch(e => { console.warn('[DOCX] arrayBuffer error:', e.message); resolve(null) })
          }, 'image/png')
        } catch (e) { console.warn('[DOCX] canvas error:', e.message); resolve(null) }
      }

      // Strategy 1: use the already-loaded <img> in the editor DOM
      const domImg = Array.from(this.editorEl?.querySelectorAll('img') || [])
        .find(el => el.src === src || el.getAttribute('src') === src)
      if (domImg?.complete && domImg.naturalWidth > 0) {
        console.warn('[DOCX] using DOM img:', src)
        return draw(domImg)
      }

      // Strategy 2: fetch → blob URL → new Image() (no crossOrigin taint risk)
      console.warn('[DOCX] fetching:', src)
      fetch(src, { credentials: 'include', cache: 'no-store' })
        .then(r => r.ok ? r.blob() : Promise.reject(new Error('HTTP ' + r.status)))
        .then(blob => {
          const blobUrl = URL.createObjectURL(blob)
          const img = new Image()
          img.onload = () => { draw(img); URL.revokeObjectURL(blobUrl) }
          img.onerror = () => { console.warn('[DOCX] blob img load failed:', src); URL.revokeObjectURL(blobUrl); resolve(null) }
          img.src = blobUrl
        })
        .catch(e => { console.warn('[DOCX] fetch error:', e.message); resolve(null) })
    })

    // Collect all image srcs from the full JSON tree
    const collectImages = (nodes) => {
      for (const node of nodes || []) {
        if (node.type === 'image' && node.attrs?.src) imageCache[node.attrs.src] = null
        collectImages(node.content)
      }
    }
    collectImages(json.content)
    console.warn('[DOCX] images to load:', Object.keys(imageCache))

    await Promise.all(Object.keys(imageCache).map(async src => {
      imageCache[src] = await loadImage(src)
    }))
    console.warn('[DOCX] image load results:', Object.fromEntries(
      Object.entries(imageCache).map(([k, v]) => [k, v ? v.width + 'x' + v.height + ' ' + v.buf.byteLength + 'b' : 'FAILED'])
    ))

    // Max image width = letter page (8.5") minus narrow margins (0.5" × 2) = 7.5" at 96dpi = 720px
    const MAX_IMG_PX = 720

    // Build a TextRun from a TipTap text node, preserving font size, family, and style marks
    const makeTextRun = (n) => {
      const markTypes = n.marks?.map(m => m.type) || []
      const styleAttrs = n.marks?.find(m => m.type === 'textStyle')?.attrs || {}
      const ptSize = parseFloat(styleAttrs.fontSize)  // stored as plain number (pt) by FontSize extension
      return new TextRun({
        text:      n.text || '',
        bold:      markTypes.includes('bold'),
        italics:   markTypes.includes('italic'),
        underline: markTypes.includes('underline') ? {} : undefined,
        strike:    markTypes.includes('strike'),
        size:      ptSize > 0 ? ptSize * 2 : 24,  // half-points; default 12pt = 24
        font:      styleAttrs.fontFamily || undefined,
      })
    }

    const children = []

    const processNode = (node) => {
      if (node.type === 'paragraph') {
        // Inline images (TipTap Image.configure({ inline: true })) live inside paragraph nodes.
        // docx requires ImageRun in its own Paragraph, so split on image children.
        const segments = []
        let runs = []
        for (const n of (node.content || [])) {
          if (n.type === 'image') {
            if (runs.length) { segments.push({ type: 'text', runs }); runs = [] }
            segments.push({ type: 'image', src: n.attrs?.src })
          } else if (n.type === 'text') {
            runs.push(makeTextRun(n))
          }
        }
        if (runs.length) segments.push({ type: 'text', runs })

        if (!segments.length) {
          children.push(new Paragraph({ children: [] }))
        } else {
          for (const seg of segments) {
            if (seg.type === 'text') {
              children.push(new Paragraph({ children: seg.runs }))
            } else {
              children.push(new Paragraph({ children: [makeImageRun(seg.src)] }))
            }
          }
        }

      } else if (node.type === 'heading') {
        const levelMap = { 1: HeadingLevel.HEADING_1, 2: HeadingLevel.HEADING_2, 3: HeadingLevel.HEADING_3 }
        const runs = (node.content || []).map(n => n.type === 'text' ? makeTextRun(n) : new TextRun(''))
        children.push(new Paragraph({ heading: levelMap[node.attrs?.level] || HeadingLevel.HEADING_1, children: runs }))

      } else if (node.type === 'bulletList' || node.type === 'orderedList') {
        ;(node.content || []).forEach(item =>
          (item.content || []).forEach(p => {
            const runs = (p.content || []).map(n => n.type === 'text' ? makeTextRun(n) : new TextRun(''))
            children.push(new Paragraph({ bullet: { level: 0 }, children: runs }))
          })
        )

      } else if (node.type === 'blockquote') {
        ;(node.content || []).forEach(p => processNode(p))

      } else if (node.type === 'image') {
        // Top-level block image (non-inline configuration)
        children.push(new Paragraph({ children: [makeImageRun(node.attrs?.src)] }))

      } else if (node.type === 'table') {
        const rows = (node.content || []).map(row =>
          new DocxTableRow({
            children: (row.content || []).map(cell =>
              new DocxTableCell({
                children: (cell.content || []).flatMap(p => {
                  if (p.type === 'paragraph') {
                    const runs = (p.content || []).map(n =>
                      n.type === 'text' ? new TextRun({ text: n.text || '' }) : new TextRun('')
                    )
                    return [new Paragraph({ children: runs })]
                  }
                  return [new Paragraph({ children: [new TextRun('')] })]
                })
              })
            )
          })
        )
        children.push(new DocxTable({ rows }))
      }
    }

    ;(json.content || []).forEach(processNode)

    if (!children.length) children.push(new Paragraph({ children: [new TextRun('')] }))

    // Narrow margins: 0.5 inch = 720 twips on all sides
    const doc = new Document({
      sections: [{
        properties: { page: { margin: { top: 720, right: 720, bottom: 720, left: 720 } } },
        children,
      }]
    })
    const blob = await Packer.toBlob(doc)
    saveAs(blob, `document-${this.fieldSlug}-${Date.now()}.docx`)
  }

  destroy() {
    this._rulerObserver?.disconnect()
    if (this._fsEscHandler) document.removeEventListener('keydown', this._fsEscHandler)
    if (this._reviewDoneHandler) window.removeEventListener('review-marked-done', this._reviewDoneHandler)
    document.body.style.overflow = ''
    this.editor?.destroy()
    this.provider?.destroy()
  }
}

export function mountEditors() {
  document.querySelectorAll('.text-editor-mount:not([data-te-mounted])').forEach(container => {
    container.setAttribute('data-te-mounted', '1')
    try {
      new TextEditorInstance(container)
    } catch (e) {
      console.error('[TextEditor] mount failed:', e)
      container.removeAttribute('data-te-mounted')
    }
  })
}

// Push all editor values into Livewire right before every request so the
// save/submit action always receives the latest editor HTML, regardless of
// whether the user typed anything or just submitted the pre-filled template.
document.addEventListener('livewire:initialized', () => {
  Livewire.hook('commit', ({ component, commit }) => {
    document.querySelectorAll('.text-editor-mount[data-te-mounted]').forEach(container => {
      const instance = container._teInstance
      if (!instance?.editor) return
      const slug = container.dataset.field
      const html = instance.editor.getHTML()
      // Only sync to Livewire on form components (hiddenInput exists, not readonly)
      if (!instance.hiddenInput || instance.readonly) return
      instance.hiddenInput.value = html
      // Also inject directly into the commit payload as a flat-key update
      if (commit.updates) commit.updates[`data.${slug}`] = html
      // Nested format fallback
      if (commit.data?.data) commit.data.data[slug] = html
    })
  })
})

export { TextEditorInstance }
