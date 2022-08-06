describe('Compare', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+\/series$/ }).as('api-packages-series')
    cy.visit('/compare/packages#packages=chromium,firefox')
    cy.wait('@api-packages-series')
  })

  it('shows title', () => {
    cy.contains('h1', 'Compare Packages')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][style]')
  })
})
