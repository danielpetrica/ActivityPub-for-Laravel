import { Node } from '@tiptap/core'

export default Node.create({
  name: 'iframe',
  group: 'block',
  atom: true,

  addAttributes() {
    return {
      src: { default: null },
      width: { default: null },
      height: { default: null },
      frameborder: { default: null },
      allow: { default: null },
      allowfullscreen: { default: null },
      referrerpolicy: { default: null },
      loading: { default: null },
      title: { default: null },
      class: { default: null },
      style: { default: null },
    }
  },

  parseHTML() {
    return [{ tag: 'iframe' }]
  },

  renderHTML({ HTMLAttributes }) {
    return ['iframe', HTMLAttributes]
  },
})
