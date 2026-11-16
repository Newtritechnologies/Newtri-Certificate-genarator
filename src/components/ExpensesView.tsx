import React, { useMemo, useState } from 'react';
import { Download, Pencil, Plus, Trash2 } from 'lucide-react';
import {
  EXPENSE_CATEGORIES,
  ExpenseRecord,
  PAYMENT_METHODS,
  PaymentMethod,
} from '../types';
import { saveExpense, voidExpense } from '../services/certService';
import { downloadCsv, formatInr } from '../utils/format';

interface ExpensesViewProps {
  expenses: ExpenseRecord[];
  loading: boolean;
  onNotify: (msg: string) => void;
}

export const ExpensesView: React.FC<ExpensesViewProps> = ({
  expenses,
  loading,
  onNotify,
}) => {
  const todayIso = new Date().toISOString().slice(0, 10);
  const currentMonth = todayIso.slice(0, 7);

  const [monthFilter, setMonthFilter] = useState<string>(currentMonth);
  const [catFilter, setCatFilter] = useState<string>('');
  const [searchQuery, setSearchQuery] = useState<string>('');

  const [editId, setEditId] = useState<string | null>(null);
  const [spentOn, setSpentOn] = useState<string>(todayIso);
  const [category, setCategory] = useState<string>(EXPENSE_CATEGORIES[0]);
  const [description, setDescription] = useState<string>('');
  const [payee, setPayee] = useState<string>('');
  const [method, setMethod] = useState<PaymentMethod>('Cash');
  const [amount, setAmount] = useState<string>('');
  const [saving, setSaving] = useState(false);
  const [err, setErr] = useState('');

  const filtered = useMemo(() => {
    const q = searchQuery.trim().toLowerCase();
    return expenses.filter((e) => {
      if (monthFilter !== 'all' && !e.spentOn.startsWith(monthFilter)) return false;
      if (catFilter && e.category !== catFilter) return false;
      if (!q) return true;
      return (
        e.description.toLowerCase().includes(q) ||
        e.payee.toLowerCase().includes(q)
      );
    });
  }, [expenses, monthFilter, catFilter, searchQuery]);

  const stats = useMemo(() => {
    const total = filtered.reduce((acc, e) => acc + e.amount, 0);
    const byCat = new Map<string, number>();
    for (const e of filtered) {
      byCat.set(e.category, (byCat.get(e.category) || 0) + e.amount);
    }
    const sortedCats = Array.from(byCat.entries()).sort((a, b) => b[1] - a[1]);
    const todayTotal = expenses
      .filter((e) => e.spentOn === todayIso)
      .reduce((acc, e) => acc + e.amount, 0);

    return {
      total,
      count: filtered.length,
      topCategory: sortedCats[0]?.[0] || '—',
      todayTotal,
      byCategory: sortedCats,
      maxCategoryAmount: sortedCats[0]?.[1] || 1,
    };
  }, [filtered, expenses, todayIso]);

  const resetForm = () => {
    setEditId(null);
    setSpentOn(todayIso);
    setCategory(EXPENSE_CATEGORIES[0]);
    setDescription('');
    setPayee('');
    setMethod('Cash');
    setAmount('');
    setErr('');
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setErr('');
    const num = Number(amount);
    if (!spentOn) {
      setErr('Choose a valid date.');
      return;
    }
    if (!description.trim()) {
      setErr('Enter what the expense was for.');
      return;
    }
    if (!Number.isFinite(num) || num <= 0 || num > 99999999.99) {
      setErr('Enter a valid amount greater than zero.');
      return;
    }

    setSaving(true);
    try {
      await saveExpense({
        id: editId || undefined,
        spentOn,
        category,
        description,
        payee,
        method,
        amount: num,
      });
      onNotify(editId ? 'Expense updated.' : 'Expense saved.');
      resetForm();
    } catch (error) {
      setErr(error instanceof Error ? error.message : 'Failed to save expense.');
    } finally {
      setSaving(false);
    }
  };

  const startEdit = (item: ExpenseRecord) => {
    setEditId(item.id);
    setSpentOn(item.spentOn);
    setCategory(item.category);
    setDescription(item.description);
    setPayee(item.payee);
    setMethod(item.method);
    setAmount(String(item.amount));
    setErr('');
  };

  const handleExport = () => {
    downloadCsv(
      `newtri-expenses-${monthFilter}.csv`,
      ['Date', 'Category', 'Description', 'Paid to', 'Paid by', 'Amount (INR)'],
      filtered.map((e) => [
        e.spentOn,
        e.category,
        e.description,
        e.payee,
        e.method,
        e.amount.toFixed(2),
      ])
    );
  };

  return (
    <div className="space-y-6">
      {/* KPI Row */}
      <section className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-xs text-slate-500">
            Total ({monthFilter === 'all' ? 'All months' : monthFilter})
          </div>
          <div className="mt-1 text-2xl font-bold text-[#0B2A5B] tabular-nums">
            {formatInr(stats.total)}
          </div>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-xs text-slate-500">Entries</div>
          <div className="mt-1 text-2xl font-bold text-slate-900 tabular-nums">{stats.count}</div>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-xs text-slate-500">Biggest Category</div>
          <div className="mt-1 text-lg font-bold text-slate-900 truncate">{stats.topCategory}</div>
        </div>
        <div className="rounded-xl border border-slate-200 bg-white p-4">
          <div className="text-xs text-slate-500">Spent Today</div>
          <div className="mt-1 text-2xl font-bold text-emerald-700 tabular-nums">
            {formatInr(stats.todayTotal)}
          </div>
        </div>
      </section>

      {/* Add / Edit Expense Form */}
      <section className="rounded-xl border border-slate-200 bg-white p-6">
        <h2 className="text-base font-bold text-slate-900">
          {editId ? 'Edit Expense' : 'Record Expense'}
        </h2>
        {err && <p className="mt-2 text-xs font-semibold text-red-700">{err}</p>}
        <form onSubmit={handleSubmit} className="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:grid-cols-6">
          <label className="text-xs font-medium text-slate-600">
            Date
            <input
              type="date"
              required
              value={spentOn}
              onChange={(e) => setSpentOn(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Category
            <select
              value={category}
              onChange={(e) => setCategory(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900"
            >
              {EXPENSE_CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
          </label>
          <label className="text-xs font-medium text-slate-600 sm:col-span-2">
            What was it for
            <input
              required
              maxLength={120}
              placeholder="e.g. Office rent or workshop supplies"
              value={description}
              onChange={(e) => setDescription(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Paid to (optional)
            <input
              maxLength={80}
              value={payee}
              onChange={(e) => setPayee(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Amount (₹)
            <input
              required
              type="number"
              min="0.01"
              step="0.01"
              value={amount}
              onChange={(e) => setAmount(e.target.value)}
              className="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-xs text-slate-900 font-mono"
            />
          </label>
          <label className="text-xs font-medium text-slate-600">
            Paid by
            <select
              value={method}
              onChange={(e) => setMethod(e.target.value as PaymentMethod)}
              className="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs text-slate-900"
            >
              {PAYMENT_METHODS.map((m) => (
                <option key={m} value={m}>
                  {m}
                </option>
              ))}
            </select>
          </label>
          <div className="flex items-end gap-2 sm:col-span-2">
            <button
              type="submit"
              disabled={saving}
              className="inline-flex items-center gap-1.5 rounded-lg bg-[#1f6feb] px-4 py-2 text-xs font-semibold text-white hover:bg-[#1558c0] disabled:opacity-50 cursor-pointer"
            >
              <Plus className="h-3.5 w-3.5" />
              <span>{editId ? 'Save Changes' : 'Save Expense'}</span>
            </button>
            {editId && (
              <button
                type="button"
                onClick={resetForm}
                className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
              >
                Cancel
              </button>
            )}
          </div>
        </form>
      </section>

      {/* Category Breakdown */}
      <section className="rounded-xl border border-slate-200 bg-white p-6">
        <h2 className="text-base font-bold text-slate-900">Category Breakdown</h2>
        {stats.byCategory.length === 0 ? (
          <p className="mt-3 text-sm text-slate-500">No expenses recorded for this filter yet.</p>
        ) : (
          <div className="mt-4 space-y-3">
            {stats.byCategory.map(([cat, val]) => {
              const widthPct = Math.max(2, Math.round((val / stats.maxCategoryAmount) * 100));
              const sharePct = stats.total > 0 ? Math.round((val / stats.total) * 100) : 0;
              return (
                <div key={cat} className="grid grid-cols-1 items-center gap-2 sm:grid-cols-12">
                  <div className="text-xs font-medium text-slate-700 sm:col-span-3">{cat}</div>
                  <div className="h-3 overflow-hidden rounded-full bg-slate-100 sm:col-span-6">
                    <div
                      className="h-full rounded-full bg-[#1f6feb]"
                      style={{ width: `${widthPct}%` }}
                    />
                  </div>
                  <div className="text-right font-mono text-xs font-semibold text-slate-800 tabular-nums sm:col-span-3">
                    {formatInr(val)} ({sharePct}%)
                  </div>
                </div>
              );
            })}
          </div>
        )}
      </section>

      {/* Ledger Table */}
      <section className="rounded-xl border border-slate-200 bg-white p-6 space-y-4">
        <div className="flex flex-wrap items-center justify-between gap-3">
          <h2 className="text-base font-bold text-slate-900">All Expenses</h2>
          <div className="flex flex-wrap items-center gap-2">
            <input
              type="month"
              value={monthFilter === 'all' ? '' : monthFilter}
              onChange={(e) => setMonthFilter(e.target.value || 'all')}
              className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-800"
            />
            <button
              type="button"
              onClick={() => setMonthFilter('all')}
              className={`rounded-lg border px-3 py-1.5 text-xs font-semibold cursor-pointer ${
                monthFilter === 'all'
                  ? 'border-[#0B2A5B] bg-[#0B2A5B] text-white'
                  : 'border-slate-300 bg-white text-slate-700'
              }`}
            >
              All Months
            </button>
            <select
              value={catFilter}
              onChange={(e) => setCatFilter(e.target.value)}
              className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-800"
            >
              <option value="">All categories</option>
              {EXPENSE_CATEGORIES.map((c) => (
                <option key={c} value={c}>
                  {c}
                </option>
              ))}
            </select>
            <input
              type="search"
              placeholder="Search description or payee"
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs text-slate-800"
            />
            <button
              type="button"
              onClick={handleExport}
              className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 cursor-pointer"
            >
              <Download className="h-3.5 w-3.5" />
              CSV
            </button>
          </div>
        </div>

        <div className="overflow-x-auto rounded-xl border border-slate-200">
          <table className="w-full min-w-[700px] border-collapse text-left text-xs">
            <thead>
              <tr className="border-b border-slate-200 bg-slate-50 text-slate-600">
                <th className="px-4 py-3 font-semibold">Date</th>
                <th className="px-4 py-3 font-semibold">Category</th>
                <th className="px-4 py-3 font-semibold">Details</th>
                <th className="px-4 py-3 font-semibold">Method</th>
                <th className="px-4 py-3 text-right font-semibold">Amount</th>
                <th className="px-4 py-3 font-semibold">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200">
              {loading && (
                <tr>
                  <td colSpan={6} className="px-4 py-8 text-center text-slate-500">
                    Loading expenses...
                  </td>
                </tr>
              )}
              {!loading && filtered.length === 0 && (
                <tr>
                  <td colSpan={6} className="px-4 py-10 text-center text-slate-500">
                    No expenses match the selected filters.
                  </td>
                </tr>
              )}
              {!loading &&
                filtered.map((item) => (
                  <tr key={item.id} className="hover:bg-slate-50">
                    <td className="px-4 py-3 font-mono tabular-nums text-slate-700">{item.spentOn}</td>
                    <td className="px-4 py-3 text-slate-700">{item.category}</td>
                    <td className="px-4 py-3">
                      <div className="font-semibold text-slate-900">{item.description}</div>
                      {item.payee && <div className="text-slate-500">{item.payee}</div>}
                    </td>
                    <td className="px-4 py-3 text-slate-700">{item.method}</td>
                    <td className="px-4 py-3 text-right font-mono font-semibold text-slate-900 tabular-nums">
                      {formatInr(item.amount)}
                    </td>
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-1.5">
                        <button
                          type="button"
                          onClick={() => startEdit(item)}
                          className="inline-flex items-center gap-1 rounded-md border border-slate-300 bg-white px-2 py-1 text-[11px] font-medium text-slate-700 hover:bg-slate-50 cursor-pointer"
                        >
                          <Pencil className="h-3 w-3" /> Edit
                        </button>
                        <button
                          type="button"
                          onClick={() =>
                            void voidExpense(item.id).then(() => onNotify('Expense removed.'))
                          }
                          className="inline-flex items-center gap-1 rounded-md border border-red-200 bg-white px-2 py-1 text-[11px] font-medium text-red-700 hover:bg-red-50 cursor-pointer"
                        >
                          <Trash2 className="h-3 w-3" /> Remove
                        </button>
                      </div>
                    </td>
                  </tr>
                ))}
            </tbody>
          </table>
        </div>
      </section>
    </div>
  );
};
