import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { createPinia, setActivePinia } from 'pinia'
import i18n from '@/i18n'
import BillingPeriodPicker from '@/components/BillingPeriodPicker.vue'



describe('BillingPeriodPicker', () => {
  it('always shows the number input, whatever the value', async () => {
    for (const value of [1, 3, 4, 6, 12, 7]) {
      const w = mount(BillingPeriodPicker, {
        props: { modelValue: value },
        global: { plugins: [i18n] },
      })
      expect(w.find('input[type="number"]').exists()).toBe(true)
      expect(w.find('input').element.value).toBe(String(value))
    }
  })

  it('lets the field be cleared without snapping back to 1', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 4 },
      global: { plugins: [i18n] },
    })
    const input = w.find('input')

    await input.setValue('')
    // The box stays empty so the next digits can be typed.
    expect(input.element.value).toBe('')
    // And it is still on screen.
    expect(w.find('input[type="number"]').exists()).toBe(true)
  })

  it('accepts a typed value that happens to be a preset', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 4 },
      global: { plugins: [i18n] },
    })
    const input = w.find('input')

    await input.setValue('12')

    expect(input.element.value).toBe('12')
    expect(w.find('input[type="number"]').exists()).toBe(true)
    expect(w.emitted('update:modelValue').at(-1)).toEqual([12])
  })

  it('emits each valid number as it is typed', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 1 },
      global: { plugins: [i18n] },
    })
    const input = w.find('input')

    await input.setValue('7')
    expect(w.emitted('update:modelValue').at(-1)).toEqual([7])

    await input.setValue('11')
    expect(w.emitted('update:modelValue').at(-1)).toEqual([11])
  })

  it('settles an empty or out-of-range field only on blur', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 5 },
      global: { plugins: [i18n] },
    })
    const input = w.find('input')

    await input.setValue('')
    await input.trigger('blur')
    expect(input.element.value).toBe('1')
    expect(w.emitted('update:modelValue').at(-1)).toEqual([1])

    await input.setValue('99')
    await input.trigger('blur')
    expect(input.element.value).toBe('24')
    expect(w.emitted('update:modelValue').at(-1)).toEqual([24])
  })

  it('fills the box when a common term is tapped', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 4 },
      global: { plugins: [i18n] },
    })

    const six = w.findAll('.preset').find((b) => b.text().includes('6'))
    await six.trigger('click')

    expect(w.find('input').element.value).toBe('6')
    expect(w.emitted('update:modelValue').at(-1)).toEqual([6])
  })

  it('highlights a preset chip only when it matches the current value', async () => {
    const w = mount(BillingPeriodPicker, {
      props: { modelValue: 4 },
      global: { plugins: [i18n] },
    })
    expect(w.findAll('.preset.on')).toHaveLength(0)

    await w.setProps({ modelValue: 3 })
    expect(w.findAll('.preset.on')).toHaveLength(1)
  })
})
