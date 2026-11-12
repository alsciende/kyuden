import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

import ModalDialog from '@/components/ModalDialog.vue'
import { isModalOpen } from '@/lib/topLayer'

enableAutoUnmount(afterEach)

type DialogPrototype = Partial<Pick<HTMLDialogElement, 'showModal' | 'close'>>

describe('ModalDialog', () => {
  const prototype = HTMLDialogElement.prototype as DialogPrototype
  const original = { showModal: prototype.showModal, close: prototype.close }

  // jsdom has no modal dialog: the methods the component calls, as a browser has them
  beforeEach(() => {
    prototype.showModal = vi.fn(function (this: HTMLDialogElement) {
      this.setAttribute('open', '')
    })
    prototype.close = vi.fn(function (this: HTMLDialogElement) {
      this.removeAttribute('open')
    })
  })

  afterEach(() => {
    prototype.showModal = original.showModal
    prototype.close = original.close
  })

  function open(props: { dismissable?: boolean } = {}) {
    return mount(ModalDialog, {
      props,
      attrs: { class: 'veil', 'aria-label': 'A card' },
      slots: { default: '<button type="button">Close</button>' },
      attachTo: document.body,
    })
  }

  it('opens as a modal dialog once mounted, named and styled by its parent', () => {
    const wrapper = open()
    const dialog = wrapper.element as HTMLDialogElement

    expect(dialog.tagName).toBe('DIALOG')
    expect(prototype.showModal).toHaveBeenCalledOnce()
    expect(dialog.open).toBe(true)
    expect(dialog.classList.contains('veil')).toBe(true)
    expect(dialog.getAttribute('aria-label')).toBe('A card')
    expect(dialog.hasAttribute('role')).toBe(false)
    expect(dialog.hasAttribute('aria-modal')).toBe(false)
    expect(wrapper.find('button').text()).toBe('Close')
  })

  it('closes on unmount, so that the focus returns to what opened it', () => {
    const wrapper = open({ dismissable: true })
    expect(isModalOpen()).toBe(true)

    wrapper.unmount()

    expect(prototype.close).toHaveBeenCalledOnce()
    expect(isModalOpen()).toBe(false)
  })

  it('asks its parent to close it on a close request when dismissable, staying open meanwhile', async () => {
    const wrapper = open({ dismissable: true })
    const cancel = new Event('cancel', { cancelable: true })

    wrapper.element.dispatchEvent(cancel)

    expect(cancel.defaultPrevented).toBe(true)
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect((wrapper.element as HTMLDialogElement).open).toBe(true)
  })

  it('ignores a close request when not dismissable', () => {
    const wrapper = open()
    const cancel = new Event('cancel', { cancelable: true })

    wrapper.element.dispatchEvent(cancel)

    expect(cancel.defaultPrevented).toBe(true)
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('opens again when the browser closes it although not dismissable', () => {
    const wrapper = open()
    const dialog = wrapper.element as HTMLDialogElement

    dialog.removeAttribute('open')
    dialog.dispatchEvent(new Event('close'))

    expect(prototype.showModal).toHaveBeenCalledTimes(2)
    expect(dialog.open).toBe(true)
    expect(wrapper.emitted('close')).toBeUndefined()
  })

  it('tells its parent when the browser closes it and it is dismissable', () => {
    const wrapper = open({ dismissable: true })
    const dialog = wrapper.element as HTMLDialogElement

    dialog.removeAttribute('open')
    dialog.dispatchEvent(new Event('close'))

    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(prototype.showModal).toHaveBeenCalledOnce()
  })

  it('is shown without the methods of a modal dialog', () => {
    delete prototype.showModal
    delete prototype.close

    const wrapper = open()

    expect((wrapper.element as HTMLDialogElement).open).toBe(true)
    expect(() => wrapper.unmount()).not.toThrow()
  })
})
