describe('Api doc', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/doc\.json$/ }).as('api-doc')
    cy.visit('/api/doc')
    cy.wait('@api-doc')
  })

  it('shows ui', () => {
    cy.get('#content').shadow().find('.swagger-ui h3[data-tag=packages]').contains('packages')
  })
})
