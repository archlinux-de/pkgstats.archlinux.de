describe('Api doc', () => {
  beforeEach(() => {
    cy.visit('/api/doc')
  })

  it('shows ui', () => {
    cy.get('#content').shadow().find('.swagger-ui h3[data-tag=packages]').contains('packages')
  })
})
