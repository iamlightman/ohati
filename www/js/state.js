// js/state.js — Ohati Global Application State

const state = {
    currentScreen: 'loading',
    previousScreens: [],
    user: null,
    vendors: [],
    categories: [],
    favorites: [],
    bookings: [],
    trackerTasks: [],
    trackerStats: { total: 0, completed: 0, overdue: 0, upcoming: 0, percentage: 0, budget: { estimated: 0, total_cost: 0, total_paid: 0, remaining: 0, outstanding: 0 } },
    event: null,
    notifications: [],
    compareList: [],

    // UI state
    selectedVendorId: null,
    activeDetailTab: 'overview',
    activeChatVendorId: null,
    activePlanningTab: 'checklist',
    expandedTaskId: null,

    // Filters
    filters: { category: '', location: '', search: '', rating: '', verified_only: 0, premium_only: 0, instant_booking: 0, min_price: '', max_price: '' },

    // Auth UI state
    authMode: 'welcome',  // welcome, account-type, register, login, otp, forgot, reset, vendor-register
    authStep: 1,
    authData: {},
    demoOTPCode: '',
    demoResetCode: '',

    // Platform reviews
    platformReviews: [],
};
