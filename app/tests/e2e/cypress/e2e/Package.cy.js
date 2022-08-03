describe('Package', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\// }).as('api')
    cy.visit('/packages/firefox')
    cy.wait('@api')
  })

  it('shows title', () => {
    cy.contains('h1', 'firefox')
  })

  it('shows chart', () => {
    // eslint-disable-next-line cypress/no-unnecessary-waiting
    cy.wait(1000)
    cy.assertCanvasIsNotEmpty('#package-chart[style]')
  })
})
