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
    platformReviews: [
        { id: 1, name: 'Abena Boateng', rating: 5, comment: 'Ohati made finding my decorator simple.<br>Verified badges gave us real confidence.', views: 476000, likes: 287000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 2, name: 'Kwame Mensah', rating: 5, comment: 'Booked our photographer through Ohati.<br>Process was seamless from start to finish.', views: 421000, likes: 254000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 3, name: 'Adjoa Sarfo', rating: 5, comment: 'Great support & easy vendor bookings.<br>Budget planner kept us on track.', views: 389000, likes: 212000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 4, name: 'Yaw Osei', rating: 5, comment: 'Best catering deals found here.<br>The comparison helper is superb.', views: 453000, likes: 271000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 5, name: 'Esi Ansah', rating: 5, comment: 'Simply the best event planning ecosystem in Ghana. Highly recommended!', views: 310000, likes: 185000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 6, name: 'Kofi Owusu', rating: 5, comment: 'Saved me tons of time negotiating with DJs and MCs. Five stars!', views: 290000, likes: 172000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 7, name: 'Ama Asare', rating: 5, comment: 'The vendor KYC and payment protection systems give me absolute peace of mind.', views: 340000, likes: 204000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" },
        { id: 8, name: 'Mustapha Ali', rating: 5, comment: 'Excellent interface, super fast load times, and amazing customer service.', views: 270000, likes: 161000, liked: false, avatar: "data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><circle cx='50' cy='50' r='50' fill='%23081729'/><circle cx='50' cy='38' r='18' fill='%23FFFFFF'/><path d='M 20 82 C 20 62, 32 56, 50 56 C 68 56, 80 62, 80 82 Z' fill='%23FFFFFF'/></svg>" }
    ],
};
