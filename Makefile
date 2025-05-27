#
# Makefile for packing up the cpviz module
#
TARBALL = ~/cpviz.tar.gz

all: sign pack

sign:
	sign cpviz

pack: $(TARBALL)

$(TARBALL):
	(cd .. ; tar cvzf $(TARBALL) --exclude=cpviz/.git --exclude=$(TARBALL) cpviz)

clean:
	rm -f $(TARBALL)
