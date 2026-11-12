import { enableAutoUnmount, mount } from '@vue/test-utils'
import { afterEach, describe, expect, it } from 'vitest'
import { defineComponent, ref } from 'vue'

import PasswordField from '@/components/PasswordField.vue'

enableAutoUnmount(afterEach)

const Form = defineComponent({
  components: { PasswordField },
  setup: () => ({ password: ref('') }),
  template: `
    <form @submit.prevent>
      <PasswordField id="password" v-model="password" autocomplete="new-password" required minlength="8" aria-describedby="hint" />
      <output>{{ password }}</output>
    </form>
  `,
})

describe('PasswordField', () => {
  it('gives what it is given to the input, hidden at first', async () => {
    const wrapper = mount(Form, { attachTo: document.body })
    const input = wrapper.get('input')

    expect(input.attributes()).toMatchObject({
      id: 'password',
      type: 'password',
      autocomplete: 'new-password',
      minlength: '8',
      'aria-describedby': 'hint',
    })
    expect(input.attributes('required')).toBeDefined()
    expect(wrapper.get('.password').attributes('id')).toBeUndefined()

    await input.setValue('ikoma-library')
    expect(wrapper.get('output').text()).toBe('ikoma-library')
  })

  it('shows and hides what was typed, keeping it', async () => {
    const wrapper = mount(Form, { attachTo: document.body })
    const input = wrapper.get('input')
    const toggle = wrapper.get('button')
    await input.setValue('ikoma-library')

    expect(toggle.text()).toBe('Show password')
    expect(toggle.attributes('type')).toBe('button')
    expect(toggle.attributes('aria-controls')).toBe('password')

    await toggle.trigger('click')
    expect(input.attributes('type')).toBe('text')
    expect(toggle.text()).toBe('Hide password')
    expect((input.element as HTMLInputElement).value).toBe('ikoma-library')

    await toggle.trigger('click')
    expect(input.attributes('type')).toBe('password')
  })

  it('hides the password again once its form is sent', async () => {
    const wrapper = mount(Form, { attachTo: document.body })
    await wrapper.get('button').trigger('click')
    expect(wrapper.get('input').attributes('type')).toBe('text')

    await wrapper.get('form').trigger('submit')

    expect(wrapper.get('input').attributes('type')).toBe('password')
  })
})
