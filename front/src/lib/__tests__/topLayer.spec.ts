import { mount } from '@vue/test-utils'
import { afterEach, describe, expect, it, vi } from 'vitest'
import { defineComponent, h, withDirectives } from 'vue'

import { isModalOpen, modalClosed, modalOpened, vTopLayer } from '@/lib/topLayer'

const Layer = defineComponent({
  render: () => withDirectives(h('div', { class: 'tooltip' }, 'Air ring'), [[vTopLayer]]),
})

describe('vTopLayer', () => {
  const prototype = HTMLElement.prototype as Partial<Pick<HTMLElement, 'showPopover'>>
  const original = prototype.showPopover

  afterEach(() => {
    prototype.showPopover = original
  })

  it('shows the element as a manual popover once mounted', () => {
    const showPopover = vi.fn()
    prototype.showPopover = showPopover

    const wrapper = mount(Layer, { attachTo: document.body })

    expect(wrapper.element.getAttribute('popover')).toBe('manual')
    expect(showPopover).toHaveBeenCalledOnce()
    wrapper.unmount()
  })

  it('leaves the element as it is in a browser without popovers', () => {
    delete prototype.showPopover

    const wrapper = mount(Layer, { attachTo: document.body })

    expect(wrapper.element.hasAttribute('popover')).toBe(false)
    wrapper.unmount()
  })
})

describe('isModalOpen', () => {
  it('counts the windows open over the page', () => {
    expect(isModalOpen()).toBe(false)

    modalOpened()
    modalOpened()
    modalClosed()
    expect(isModalOpen()).toBe(true)

    modalClosed()
    modalClosed()
    expect(isModalOpen()).toBe(false)
  })
})
