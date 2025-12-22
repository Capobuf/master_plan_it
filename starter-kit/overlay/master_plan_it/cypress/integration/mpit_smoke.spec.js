// MPIT UI smoke test (Cypress)
// Intention: ensure login works and Desk loads.
//
// Run:
//   cd apps/master_plan_it
//   yarn
//   yarn cypress:open
//
describe('MPIT smoke', () => {
  it('loads login page', () => {
    cy.visit('/login');
    cy.contains('Login');
  });
});
