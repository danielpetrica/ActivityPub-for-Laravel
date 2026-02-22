import { Node } from '@tiptap/core'

export default Node.create({
  name: 'figcaption',
  group: 'block',
  content: 'inline*',

  parseHTML() {
    return [{ tag: 'figcaption' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['figcaption', HTMLAttributes, 0]
  },
})
