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
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][data-test-rendered=true][style]')
  })
})
