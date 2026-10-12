import { describe, expect, it } from 'vitest';
import { addTax, applyDiscount, CalculatorError, evaluate, removeTax, roundMoney, settle, tryEvaluate } from '@/utils/calculator';

describe('evaluate', () => {
    it('does arithmetic with the usual precedence', () => {
        expect(evaluate('2 + 3 × 4')).toBe(14);
        expect(evaluate('(2 + 3) × 4')).toBe(20);
        expect(evaluate('10 ÷ 4')).toBe(2.5);
        expect(evaluate('10 - 2 - 3')).toBe(5);
        expect(evaluate('8 ÷ 2 ÷ 2')).toBe(2);
    });

    it('accepts keyboard operators and the minus sign', () => {
        expect(evaluate('6*7')).toBe(42);
        expect(evaluate('9/3')).toBe(3);
        expect(evaluate('5 − 2')).toBe(3);
    });

    it('handles unary minus and decimals without float noise', () => {
        expect(evaluate('-5 + 2')).toBe(-3);
        expect(evaluate('2 × -3')).toBe(-6);
        expect(evaluate('0.1 + 0.2')).toBe(0.3);
        expect(evaluate('.5 + .5')).toBe(1);
    });

    it('treats percent like a pocket calculator', () => {
        expect(evaluate('200 + 10%')).toBe(220);
        expect(evaluate('200 − 10%')).toBe(180);
        expect(evaluate('200 × 10%')).toBe(20);
        expect(evaluate('200 ÷ 50%')).toBe(400);
        expect(evaluate('10%')).toBe(0.1);
        expect(evaluate('(100 + 100) − 25%')).toBe(150);
    });

    it('rejects what it cannot compute', () => {
        expect(() => evaluate('1 ÷ 0')).toThrow(CalculatorError);
        expect(() => evaluate('1 +')).toThrow(CalculatorError);
        expect(() => evaluate('(1 + 2')).toThrow(CalculatorError);
        expect(() => evaluate('1 + 2)')).toThrow(CalculatorError);
        expect(() => evaluate('1..2 + 1')).toThrow(CalculatorError);
        expect(() => evaluate('2 ^ 3')).toThrow(CalculatorError);
        expect(() => evaluate('alert(1)')).toThrow(CalculatorError);
    });

    it('never executes code', () => {
        expect(() => evaluate('constructor.constructor("return 1")()')).toThrow(CalculatorError);
    });
});

describe('tryEvaluate', () => {
    it('returns null for empty or incomplete input instead of throwing', () => {
        expect(tryEvaluate('')).toBeNull();
        expect(tryEvaluate('   ')).toBeNull();
        expect(tryEvaluate('5 +')).toBeNull();
        expect(tryEvaluate('5 + 5')).toBe(10);
    });
});

describe('point of sale helpers', () => {
    it('rounds to cents', () => {
        expect(roundMoney(1.005)).toBe(1.01);
        expect(roundMoney(2.344)).toBe(2.34);
    });

    it('works out change and remaining balance', () => {
        expect(settle(80, 100)).toEqual({ change: 20, remaining: 0 });
        expect(settle(80, 30)).toEqual({ change: 0, remaining: 50 });
        expect(settle(80, 80)).toEqual({ change: 0, remaining: 0 });
        expect(settle(80, '')).toEqual({ change: 0, remaining: 80 });
    });

    it('adds and removes tax', () => {
        expect(addTax(100, 11)).toBe(111);
        expect(removeTax(111, 11)).toBe(100);
        expect(addTax(19.99, 10)).toBe(21.99);
    });

    it('applies a percentage discount', () => {
        expect(applyDiscount(200, 15)).toBe(170);
    });
});
