describe('Compare', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\// }).as('api')
    cy.visit('/compare/packages#packages=chromium,firefox')
    cy.wait('@api')
  })

  it('shows title', () => {
    cy.contains('h1', 'Compare Packages')
  })

  it('shows chart', () => {
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][style]')
  })
})
