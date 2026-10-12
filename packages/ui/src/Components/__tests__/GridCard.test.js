import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import GridCard from '../GridCard.vue';

describe('GridCard', () => {
    it('renders every slot in its region', () => {
        const wrapper = mount(GridCard, {
            slots: {
                media: '<img alt="photo" />',
                title: 'Chicken shawarma',
                subtitle: 'SHAWARMA',
                meta: '<span>LBP 450,000</span>',
                actions: '<button>edit</button>',
            },
        });

        expect(wrapper.find('img').exists()).toBe(true);
        expect(wrapper.get('[data-card-title]').text()).toBe('Chicken shawarma');
        expect(wrapper.get('[data-card-subtitle]').text()).toBe('SHAWARMA');
        expect(wrapper.get('[data-card-meta]').text()).toBe('LBP 450,000');
        expect(wrapper.get('[data-card-actions]').text()).toBe('edit');
    });

    it('omits empty regions', () => {
        const wrapper = mount(GridCard, { slots: { title: 'Only a title' } });

        expect(wrapper.find('[data-card-media]').exists()).toBe(false);
        expect(wrapper.find('[data-card-actions]').exists()).toBe(false);
    });
});
