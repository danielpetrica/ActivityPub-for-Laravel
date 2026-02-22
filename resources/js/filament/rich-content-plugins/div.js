import { Node } from '@tiptap/core'

export default Node.create({
  name: 'div',
  group: 'block',
  content: 'block*',

  addAttributes() {
    return {
      id: { default: null },
      class: { default: null },
      style: { default: null },
    }
  },

  parseHTML() {
    return [{ tag: 'div' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['div', HTMLAttributes, 0]
  },
})
