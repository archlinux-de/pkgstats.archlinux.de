describe('Compare', () => {
  beforeEach(() => {
    cy.intercept({ method: 'GET', pathname: /^\/api\/packages\/[\w-]+\/series$/ }).as('api-packages-series')
  })

  it('shows title', () => {
    cy.visit('/compare/packages#packages=chromium,firefox')
    cy.wait('@api-packages-series')
    cy.contains('h1', 'Compare Packages')
  })

  it('shows chart', () => {
    cy.visit('/compare/packages#packages=chromium,firefox')
    cy.wait('@api-packages-series')
    cy.assertCanvasIsNotEmpty('[data-test=package-chart][style]')
  })

  it('sorts requested packages in URL', () => {
    cy.visit('/compare/packages#packages=linux,bash')
    cy.wait('@api-packages-series')

    cy.location().should((loc) => {
      expect(loc.hash).to.eq('#packages=bash,linux')
    })
  })

  it('removes duplicates from requested packages in URL', () => {
    cy.visit('/compare/packages#packages=linux,bash,linux')
    cy.wait('@api-packages-series')

    cy.location().should((loc) => {
      expect(loc.hash).to.eq('#packages=bash,linux')
    })
  })
})
